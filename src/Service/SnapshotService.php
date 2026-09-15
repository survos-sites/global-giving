<?php

declare(strict_types=1);

namespace App\Service;

use Survos\GlobalGiving\Enum\Export;
use Survos\GlobalGiving\GlobalGivingClient;
use Survos\GlobalGiving\Xml\ExportReader;
use Survos\JsonlBundle\IO\JsonlWriter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final readonly class SnapshotService
{
    public function __construct(private GlobalGivingClient $client, private ExportReader $reader, #[\Symfony\Component\DependencyInjection\Attribute\Autowire(service: 'app.catalog_serializer')] private NormalizerInterface $normalizer, private Filesystem $fs, #[Autowire('%kernel.project_dir%/var/exports')] private string $directory) {}

    /** @return array{path:string, count:int, bytes:int, fetchedAt:string, dataset:string, sha256:string} */
    public function prepare(Export $dataset, bool $refresh = false, ?string $file = null): array
    {
        $root = $this->directory.'/'.$dataset->value;
        $this->fs->mkdir($root);
        $manifest = $root.'/current.json';
        if (!$refresh && $file === null && is_file($manifest)) {
            $cached = json_decode(file_get_contents($manifest), true, flags: JSON_THROW_ON_ERROR);
            if (($cached['remote'] ?? false) && strtotime($cached['fetchedAt']) > time() - 86400 && is_file($cached['path'])) {
                $digest = hash_file('sha256', $cached['path']);
                if (isset($cached['sha256']) && !hash_equals($cached['sha256'], $digest)) { throw new \RuntimeException('Cached snapshot checksum mismatch; fetch a fresh snapshot with --refresh.'); }
                $cached['sha256'] = $digest;
                return $cached;
            }
        }
        $dir = $root.'/'.bin2hex(random_bytes(8));
        $this->fs->mkdir($dir);
        try {
            $xml = $file ?? $dir.'/source.xml';
            $bytes = $file === null ? $this->client->download($dataset, $xml) : filesize($xml);
            $path = $dir.'/records.jsonl';
            $writer = JsonlWriter::open($path);
            $count = 0;
            try {
                foreach ($this->reader->read($xml, $dataset) as $dto) {
                    $writer->write($this->normalizer->normalize($dto));
                    ++$count;
                }
            } finally { $writer->close(); }
            $result = ['path' => $path, 'count' => $count, 'bytes' => $bytes, 'fetchedAt' => gmdate(DATE_ATOM), 'dataset' => $dataset->value, 'remote' => $file === null, 'sha256' => hash_file('sha256', $path)];
            $this->fs->dumpFile($dir.'/manifest.json', json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
            if ($file === null) { $this->fs->dumpFile($manifest, json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)); }
            return $result;
        } catch (\Throwable $error) {
            $this->fs->remove($dir);
            throw $error;
        }
    }
}
