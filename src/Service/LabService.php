<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Project;
use App\Input\LabInput;
use Doctrine\ORM\EntityManagerInterface;
use Meilisearch\Contracts\Task;
use Meilisearch\Contracts\TaskStatus;
use Survos\JsonlBundle\IO\JsonlWriter;
use Survos\MeiliBundle\Service\MeiliService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\MapInput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final readonly class LabService
{
    public function __construct(
        private EntityManagerInterface $em, private MeiliService $meili, private UrlGeneratorInterface $urls, private LockFactory $locks,
        #[Autowire(service: 'app.catalog_serializer')] private NormalizerInterface $normalizer,
        #[Autowire('%env(OLLAMA_EMBED_URL)%')] private string $embedUrl,
        #[Autowire('%env(OLLAMA_EMBED_MODEL)%')] private string $model,
        #[Autowire('%kernel.project_dir%/var/lab')] private string $directory,
    ) {}

    #[AsCommand('app:lab', 'Build a small local semantic-search experiment with source provenance')]
    public function build(SymfonyStyle $io, #[MapInput] LabInput $input): int
    {
        $lock = $this->locks->createLock('global-giving-import');
        if (!$lock->acquire()) { $io->error('Another catalog job is running.'); return Command::FAILURE; }
        $client = $this->meili->getMeiliClient();
        $uid = $this->meili->uidForRaw('lab');
        $temporary = $uid.'_build_'.bin2hex(random_bytes(4));
        $fs = new Filesystem(); $fs->mkdir($this->directory);
        try {
            $this->wait($client->createIndex($temporary, ['primaryKey' => 'id']));
            $index = $client->index($temporary);
            $this->wait($index->updateSettings([
                'searchableAttributes' => ['title', 'summary', 'need', 'activities', 'longTermImpact', 'themes'],
                'filterableAttributes' => ['countries', 'themes', 'active'],
                'embedders' => ['local' => [
                    'source' => 'ollama', 'model' => $this->model, 'url' => $this->embedUrl,
                    'documentTemplate' => '{{doc.embeddingText}}', 'documentTemplateMaxBytes' => 6000,
                ]],
            ]));
            $path = $this->directory.'/'.$temporary.'.jsonl';
            $writer = JsonlWriter::open($path);
            $count = 0;
            $after = 0;
            try {
                do {
                    $rows = $this->em->createQuery('SELECT p FROM App\\Entity\\Project p WHERE p.active = true AND p.id > :after ORDER BY p.id')->setParameter('after', $after)->setMaxResults(min(50, $input->limit - $count))->getResult();
                    $documents = [];
                    foreach ($rows as $project) {
                        $document = $this->normalizer->normalize($project, context: ['groups' => ['public']]);
                        $document['showUrl'] = $this->urls->generate('project_show', $project->getRp());
                        $document['embeddingText'] = $project->embeddingText;
                        $documents[] = $document;
                        $writer->write(['id' => $project->id, 'text' => $project->embeddingText, 'textHash' => hash('sha256', $project->embeddingText), 'sourceUrl' => $project->projectUrl, 'sourceModifiedAt' => $project->modifiedDate, 'model' => $this->model]);
                        $after = $project->id; ++$count;
                    }
                    if ($documents !== []) { $this->wait($index->addDocuments($documents)); $io->writeln($count.' / '.$input->limit.' embedded'); }
                    $this->em->clear();
                } while ($rows !== [] && $count < $input->limit);
            } finally { $writer->close(); }
            try { $client->index($uid)->fetchRawInfo(); }
            catch (\Meilisearch\Exceptions\ApiException $e) {
                if ($e->httpStatus !== 404) { throw $e; }
                $this->wait($client->createIndex($uid, ['primaryKey' => 'id']));
            }
            $this->wait($client->swapIndexes([[$temporary, $uid]]));
            $fs->dumpFile($this->directory.'/current.json', json_encode(['count' => $count, 'model' => $this->model, 'embedder' => 'local', 'builtAt' => gmdate(DATE_ATOM), 'records' => $path], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
            $io->success($count.' projects ready at /lab. Local model: '.$this->model.'. No cloud inference was used.');
            return Command::SUCCESS;
        } finally { $client->deleteIndex($temporary); $lock->release(); }
    }

    private function wait(Task $task): void
    {
        $result = $task->wait(300000, 200);
        if ($result->getStatus() !== TaskStatus::Succeeded) { throw new \RuntimeException($result->getError()->message ?? 'Embedding task failed.'); }
    }
}
