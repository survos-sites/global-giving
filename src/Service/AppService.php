<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\Theme;
use App\Input\ImportInput;
use Doctrine\ORM\EntityManagerInterface;
use Survos\GlobalGiving\Dto\Organization as OrganizationData;
use Survos\GlobalGiving\Enum\Export;
use Survos\GlobalGiving\Serialization\RecordMapper;
use Survos\JsonlBundle\IO\JsonlReader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\MapInput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Lock\LockFactory;
use Zenstruck\Bytes;

final readonly class AppService
{
    public function __construct(private SnapshotService $snapshots, private EntityManagerInterface $em, private RecordMapper $mapper, private LockFactory $locks) {}

    #[AsCommand('app:import', 'Download, validate and import GlobalGiving data')]
    public function import(SymfonyStyle $io, #[MapInput] ImportInput $input): int
    {
        $lock = $this->locks->createLock('global-giving-import');
        if (!$lock->acquire()) { $io->error('An import is already running.'); return Command::FAILURE; }
        try {
            $io->note('Preparing '.$input->dataset->value.' snapshot.');
            $snapshot = $this->snapshots->prepare($input->dataset, $input->refresh, $input->file);
            $io->writeln(sprintf('Validated %d records (%s).', $snapshot['count'], Bytes::parse($snapshot['bytes'])));
            $count = $this->importSnapshot($snapshot['path'], $input->dataset, $input->limit, $snapshot['count'], $snapshot['sha256'], $input->file === null);
            $io->success(sprintf('Imported %d records. Peak PHP memory: %s.', $count, Bytes::parse(memory_get_peak_usage(true))));
            return Command::SUCCESS;
        } finally { $lock->release(); }
    }

    public function importSnapshot(string $path, Export $dataset, int $limit = 0, ?int $expectedCount = null, ?string $sha256 = null, bool $reconcile = true): int
    {
        if ($limit < 0) { throw new \InvalidArgumentException('Limit must be nonnegative.'); }
        if ($sha256 !== null && !hash_equals($sha256, hash_file('sha256', $path))) { throw new \RuntimeException('Snapshot checksum mismatch.'); }
        $count = 0;
        $run = bin2hex(random_bytes(16));
        $time = new \DateTimeImmutable();
        $this->em->beginTransaction();
        try {
            foreach (JsonlReader::open($path) as $row) {
                if ($dataset === Export::Organizations) {
                    $dto = $this->mapper->organization($row['source']);
                    $this->organization($dto, $time);
                } else {
                    $dto = $this->mapper->project($row['source']);
                    $org = $dto->organization ? $this->organization($dto->organization, $time) : null;
                    $project = $this->em->find(Project::class, $dto->id) ?? new Project($dto->id);
                    $project->update($dto, $org, $time, $run);
                    $this->em->persist($project);
                }
                foreach ($dto->themes as $code => $label) {
                    $theme = $this->em->find(Theme::class, $code) ?? new Theme($code);
                    $theme->label = $label;
                    $this->em->persist($theme);
                }
                ++$count;
                if ($count % 200 === 0) { $this->em->flush(); $this->em->clear(); }
                if ($limit > 0 && $count >= $limit) { break; }
            }
            if ($expectedCount !== null && $count !== ($limit > 0 ? min($limit, $expectedCount) : $expectedCount)) { throw new \RuntimeException('Normalized snapshot record count mismatch.'); }
            $this->em->flush();
            if ($reconcile && $limit === 0 && $dataset !== Export::Organizations) {
                // Only complete validated project snapshots can retire previously active records.
                $this->em->createQuery('UPDATE App\\Entity\\Project p SET p.active = false WHERE p.importRun != :run')->setParameter('run', $run)->execute();
            }
            $this->em->commit();
            $this->em->clear();
            return $count;
        } catch (\Throwable $error) {
            $this->em->rollback(); $this->em->clear(); throw $error;
        }
    }

    private function organization(OrganizationData $dto, \DateTimeImmutable $time): Organization
    {
        $org = $this->em->find(Organization::class, $dto->id) ?? new Organization($dto->id);
        $org->update($dto, $time);
        $this->em->persist($org);
        return $org;
    }

    #[AsCommand('app:status', 'Show imported catalog counts')]
    public function status(SymfonyStyle $io): int
    {
        $io->table(['Entity', 'Records'], array_map(fn(string $class): array => [(new \ReflectionClass($class))->getShortName(), $this->em->getRepository($class)->count([])], [Project::class, Organization::class, Theme::class]));
        return Command::SUCCESS;
    }
}
