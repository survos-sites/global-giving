<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Project;
use App\Entity\Organization;
use Doctrine\ORM\EntityManagerInterface;
use Survos\FieldBundle\Service\FieldReader;
use Survos\MeiliBundle\Service\MeiliService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final readonly class SearchService
{
    public function __construct(private MeiliService $meili, private EntityManagerInterface $em, private FieldReader $fields, #[\Symfony\Component\DependencyInjection\Attribute\Autowire(service: 'app.catalog_serializer')] private NormalizerInterface $normalizer, private UrlGeneratorInterface $urls, private LockFactory $locks) {}

    #[AsCommand('app:index', 'Build and publish project and organization search indexes')]
    public function index(SymfonyStyle $io): int
    {
        $lock = $this->locks->createLock('global-giving-import');
        if (!$lock->acquire()) { $io->error('An import or indexing run is already running.'); return Command::FAILURE; }
        try {
            foreach (['projects' => Project::class, 'organizations' => Organization::class] as $base => $class) {
                $io->writeln($base.': '.$this->populate($base, $class).' documents published.');
            }
            return Command::SUCCESS;
        } finally { $lock->release(); }
    }

    private function populate(string $base, string $class): int
    {
        $client = $this->meili->getMeiliClient();
        $uid = $this->meili->uidForRaw($base);
        $temporary = $uid.'_build_'.bin2hex(random_bytes(4));
        $this->wait($client->createIndex($temporary, ['primaryKey' => 'id']));
        $index = $client->index($temporary);
        try {
            $settings = ['searchableAttributes' => [], 'sortableAttributes' => [], 'filterableAttributes' => []];
            foreach ($this->fields->getDescriptors($class) as $field) {
                foreach (['searchable' => 'searchableAttributes', 'sortable' => 'sortableAttributes', 'filterable' => 'filterableAttributes'] as $flag => $key) {
                    if ($field->$flag || ($flag === 'filterable' && $field->facet)) { $settings[$key][] = $field->name; }
                }
            }
            $this->wait($index->updateSettings($settings));
            $count = 0;
            $after = 0;
            do {
                $entities = $this->em->createQuery('SELECT e FROM '.$class.' e WHERE e.id > :after ORDER BY e.id')->setParameter('after', $after)->setMaxResults(500)->getResult();
                $batch = [];
                foreach ($entities as $entity) {
                    $document = $this->normalizer->normalize($entity, context: ['groups' => ['public']]);
                    $document['showUrl'] = $this->urls->generate($class === Project::class ? 'project_show' : 'organization_show', $entity->getRp());
                    $batch[] = $document;
                    $after = $entity->id;
                    ++$count;
                }
                if ($batch !== []) { $this->wait($index->addDocuments($batch, 'id')); }
                $this->em->clear();
            } while ($entities !== []);
            try { $client->index($uid)->fetchRawInfo(); }
            catch (\Meilisearch\Exceptions\ApiException $e) {
                if ($e->httpStatus !== 404) { throw $e; }
                $this->wait($client->createIndex($uid, ['primaryKey' => 'id']));
            }
            $this->wait($client->swapIndexes([[$temporary, $uid]]));
            return $count;
        } finally {
            $this->wait($client->deleteIndex($temporary));
            $this->em->clear();
        }
    }

    private function wait(\Meilisearch\Contracts\Task $task): void
    {
        $result = $task->wait(120000, 100);
        if ($result->getStatus() !== \Meilisearch\Contracts\TaskStatus::Succeeded) { throw new \RuntimeException('Meilisearch task failed: '.($result->getError()->message ?? 'unknown error')); }
    }
}
