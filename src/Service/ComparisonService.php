<?php

declare(strict_types=1);

namespace App\Service;

use App\Input\LabInput;
use App\Lab\LabGateway;
use App\Lab\Ranking;
use Doctrine\ORM\EntityManagerInterface;
use Survos\JsonlBundle\IO\JsonlReader;
use Survos\JsonlBundle\IO\JsonlWriter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\MapInput;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Lock\LockFactory;

final readonly class ComparisonService
{
    public function __construct(private LabGateway $gateway, private EntityManagerInterface $em, private Filesystem $fs, private LockFactory $locks,
        #[Autowire('%kernel.project_dir%/var/lab/compare')] private string $directory) {}

    #[AsCommand('app:lab:snapshot', 'Freeze a shared project sample and generate reusable local embeddings')]
    public function snapshot(SymfonyStyle $io, #[MapInput] LabInput $input): int
    {
        $lock = $this->locks->createLock('global-giving-import');
        if (!$lock->acquire()) { $io->error('Another catalog job is running.'); return Command::FAILURE; }
        try {
            $digest = $this->gateway->modelDigest();
            $this->fs->mkdir($this->directory);
            $path = $this->directory.'/snapshot-'.bin2hex(random_bytes(8)).'.jsonl';
            $writer = JsonlWriter::open($path); $count = 0; $dimensions = null;
            $rows = $this->em->createQuery('SELECT p FROM App\\Entity\\Project p WHERE p.active = true ORDER BY p.id')->setMaxResults($input->limit)->getResult();
            try {
                foreach ($rows as $p) {
                    // Bound model input explicitly, preserving exactly what was embedded.
                    $text = mb_strcut($p->embeddingText, 0, 6000, 'UTF-8');
                    $vector = $this->embedding($text, $digest);
                    $dimensions ??= count($vector);
                    if (count($vector) !== $dimensions) { throw new \RuntimeException('Embedding dimensions changed during snapshot.'); }
                    $writer->write(['id' => $p->id, 'title' => $p->title, 'summary' => $p->summary, 'countries' => $p->countries, 'themes' => $p->themes, 'text' => $text, 'textHash' => hash('sha256', $text), 'sourceUrl' => $p->projectUrl, 'sourceModifiedAt' => $p->modifiedDate, 'vector' => $vector]);
                    ++$count;
                    if ($count % 25 === 0) { $io->writeln($count.' embedded'); }
                }
            } finally { $writer->close(); }
            if (!$count) { throw new \RuntimeException('No active projects. Import a catalog first.'); }
            if ($this->gateway->modelDigest() !== $digest) { throw new \RuntimeException('Model changed during snapshot; retry.'); }
            $manifest = ['snapshot' => hash_file('sha256', $path), 'records' => basename($path), 'count' => $count, 'dimensions' => $dimensions, 'model' => $this->gateway->model, 'modelDigest' => $digest, 'builtAt' => gmdate(DATE_ATOM), 'selection' => 'active projects ordered by source ID', 'textMaxBytes' => 6000];
            $this->write('current', $manifest);
            $io->success($count.' frozen projects. Index each engine with app:lab:index meili and app:lab:index elastic.');
            return Command::SUCCESS;
        } finally { $lock->release(); }
    }

    #[AsCommand('app:lab:index', 'Index the frozen comparison snapshot into meili or elastic')]
    public function index(SymfonyStyle $io, #[Argument('meili or elastic')] string $engine): int
    {
        if (!in_array($engine, ['meili', 'elastic'], true)) { $io->error('Choose meili or elastic.'); return Command::INVALID; }
        $lock = $this->locks->createLock('gg-lab-index-'.$engine);
        if (!$lock->acquire()) { $io->error('This engine is already indexing.'); return Command::FAILURE; }
        try {
            $manifest = $this->manifest();
            $name = 'gg_compare_'.substr($manifest['snapshot'], 0, 12).'_'.bin2hex(random_bytes(4));
            $start = microtime(true);
            if ($engine === 'elastic') {
                $this->gateway->request($engine, 'PUT', $name, ['settings' => ['number_of_shards' => 1, 'number_of_replicas' => 0], 'mappings' => ['dynamic' => false, 'properties' => ['id' => ['type' => 'long'], 'text' => ['type' => 'text'], 'countries' => ['type' => 'keyword'], 'themes' => ['type' => 'keyword'], 'vector' => ['type' => 'dense_vector', 'dims' => $manifest['dimensions'], 'index' => true, 'similarity' => 'cosine']]]]);
            } else {
                $this->gateway->wait($this->gateway->request($engine, 'POST', 'indexes', ['uid' => $name, 'primaryKey' => 'id']));
                $this->gateway->wait($this->gateway->request($engine, 'PATCH', 'indexes/'.$name.'/settings', ['searchableAttributes' => ['text'], 'filterableAttributes' => ['countries', 'themes'], 'embedders' => ['shared' => ['source' => 'userProvided', 'dimensions' => $manifest['dimensions']]]]));
            }
            $count = 0; $batch = [];
            foreach (JsonlReader::open($this->directory.'/'.$manifest['records']) as $row) {
                if ($engine === 'elastic') { $this->gateway->request($engine, 'PUT', $name.'/_doc/'.$row['id'], $row); }
                else {
                    $row['_vectors'] = ['shared' => $row['vector']]; unset($row['vector']); $batch[] = $row;
                    if (count($batch) === 25) { $this->gateway->wait($this->gateway->request($engine, 'POST', 'indexes/'.$name.'/documents', $batch)); $batch = []; }
                }
                ++$count;
            }
            if ($batch !== []) { $this->gateway->wait($this->gateway->request($engine, 'POST', 'indexes/'.$name.'/documents', $batch)); }
            if ($engine === 'elastic') {
                $this->gateway->request($engine, 'POST', $name.'/_refresh');
                $actual = $this->gateway->request($engine, 'GET', $name.'/_count')['count'];
            } else { $actual = $this->gateway->request($engine, 'GET', 'indexes/'.$name.'/stats')['numberOfDocuments']; }
            if ($actual !== $count || $count !== $manifest['count']) { throw new \RuntimeException('Index count does not match snapshot.'); }
            // Only publish the index pointer after every record is searchable.
            $this->write($engine, ['snapshot' => $manifest['snapshot'], 'index' => $name, 'count' => $count, 'indexSeconds' => round(microtime(true) - $start, 2)]);
            $io->success($engine.': '.$count.' records ready.');
            return Command::SUCCESS;
        } finally { $lock->release(); }
    }

    public function manifest(): array
    {
        $manifest = $this->read('current');
        if ($manifest === []) { throw new \RuntimeException('Create a comparison snapshot with app:lab:snapshot first.'); }
        if (hash_file('sha256', $this->directory.'/'.$manifest['records']) !== $manifest['snapshot']) { throw new \RuntimeException('Snapshot checksum mismatch.'); }
        return $manifest;
    }

    public function compare(string $query, string $mode, string $country): array
    {
        $manifest = $this->manifest(); $vector = null; $embeddingMs = 0;
        if ($mode !== 'keyword') {
            try {
                $start = microtime(true);
                if ($this->gateway->modelDigest() !== $manifest['modelDigest'] || $this->gateway->model !== $manifest['model']) { throw new \RuntimeException(); }
                $vector = $this->embedding($query, $manifest['modelDigest']);
                $embeddingMs = round((microtime(true) - $start) * 1000, 1);
            } catch (\Throwable) { throw new \RuntimeException('Query embedding unavailable or model changed. Restore the snapshot model or build a new snapshot.'); }
        }
        $results = [];
        foreach (['meili', 'elastic'] as $engine) {
            try {
                $state = $this->read($engine);
                if (($state['snapshot'] ?? '') !== $manifest['snapshot']) { throw new \RuntimeException('Index this snapshot with app:lab:index '.$engine.'.'); }
                $start = microtime(true);
                $keyword = $mode !== 'vector' ? $this->gateway->search($engine, $state['index'], $query, null, $country) : [];
                $semantic = $mode !== 'keyword' ? $this->gateway->search($engine, $state['index'], $query, $vector, $country) : [];
                $hits = $mode === 'hybrid' ? Ranking::fuse($keyword, $semantic) : array_slice($mode === 'vector' ? $semantic : $keyword, 0, 10);
                $results[$engine] = ['hits' => $hits, 'elapsedMs' => round((microtime(true) - $start) * 1000, 1), 'index' => $state['index'], 'error' => null];
            } catch (\RuntimeException $e) { $results[$engine] = ['hits' => [], 'error' => $e->getMessage()]; }
        }
        return ['query' => $query, 'mode' => $mode, 'country' => $country, 'snapshot' => $manifest['snapshot'], 'model' => $manifest['model'], 'modelDigest' => $manifest['modelDigest'], 'embeddingMs' => $embeddingMs, 'results' => $results, 'createdAt' => gmdate(DATE_ATOM), 'candidateLimit' => 50, 'rrfConstant' => 60];
    }

    public function saveRun(array $run): string
    {
        $id = bin2hex(random_bytes(12)); $this->write('runs/'.$id, $run); return $id;
    }

    public function run(string $id): array { return $this->read('runs/'.$this->validId($id)); }

    public function saved(): array
    {
        if (!is_dir($this->directory.'/runs')) { return []; }
        $runs = [];
        foreach ((new Finder())->files()->in($this->directory.'/runs')->name('*.json')->sortByModifiedTime()->reverseSorting() as $file) {
            $runs[$file->getBasename('.json')] = json_decode($file->getContents(), true, flags: JSON_THROW_ON_ERROR);
            if (count($runs) === 20) { break; }
        }
        return $runs;
    }

    public function judge(string $runId, int $projectId, int $grade): void
    {
        $run = $this->run($runId);
        $ids = [];
        foreach ($run['results'] ?? [] as $result) { $ids = array_merge($ids, array_column($result['hits'], 'id')); }
        if (!in_array($projectId, $ids, true) || !in_array($grade, [-1, 0, 1, 2], true)) { throw new \InvalidArgumentException('Invalid judgment.'); }
        if ($grade === -1) { $this->fs->remove($this->directory.'/judgments/'.$this->judgmentKey($run).'/'.$projectId.'.json'); return; }
        $this->write('judgments/'.$this->judgmentKey($run).'/'.$projectId, ['grade' => $grade, 'projectId' => $projectId, 'query' => $run['query'], 'country' => $run['country'], 'snapshot' => $run['snapshot'], 'updatedAt' => gmdate(DATE_ATOM)]);
    }

    public function judgments(array $run): array
    {
        $grades = [];
        foreach ($run['results'] as $result) {
            foreach ($result['hits'] as $hit) { $grades[$hit['id']] = $this->read('judgments/'.$this->judgmentKey($run).'/'.$hit['id'])['grade'] ?? null; }
        }
        return $grades;
    }

    private function judgmentKey(array $run): string { return hash('sha256', json_encode([$run['snapshot'], $run['query'], $run['country']], JSON_THROW_ON_ERROR)); }
    private function validId(string $id): string { if (!preg_match('/^[a-f0-9]{24}$/D', $id)) { throw new \InvalidArgumentException('Invalid run ID.'); } return $id; }
    private function embedding(string $text, string $digest): array
    {
        $key = 'embeddings/'.hash('sha256', $digest."\0".$text); $cached = $this->read($key);
        if ($cached !== []) { return $cached['vector']; }
        $vector = $this->gateway->embed($text); $this->write($key, ['vector' => $vector]); return $vector;
    }
    private function read(string $name): array { $path = $this->directory.'/'.$name.'.json'; return is_file($path) ? json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR) : []; }
    private function write(string $name, array $data): void { $this->fs->dumpFile($this->directory.'/'.$name.'.json', json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)); }
}
