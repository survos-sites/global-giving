<?php

namespace App\Command;

use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\Theme;
use App\Repository\OrganizationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Survos\GlobalGivingBundle\Service\GlobalGivingService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Zenstruck\Console\Attribute\Option;
use Zenstruck\Console\ConfigureWithAttributes;
use Zenstruck\Console\InvokableServiceCommand;
use Zenstruck\Console\IO;
use Zenstruck\Console\RunsCommands;
use Zenstruck\Console\RunsProcesses;

#[AsCommand('app:load-data', 'use the global giving api to load the projects and organizations')]
final class AppLoadDataCommand extends InvokableServiceCommand
{
    use ConfigureWithAttributes;
    use RunsCommands;
    use RunsProcesses;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private GlobalGivingService $globalGivingService,
        private OrganizationRepository $organizationRepository,
        private PropertyAccessorInterface $propertyAccessor,
        private HttpClientInterface $httpClient,
        private $existingObjects = []
    )
    {
        parent::__construct();
    }

    public function __invoke(
        IO   $io,

        #[Option(description: 'limit the number of records fetched and loaded', shortcut: 'l')]
        int  $limit=0,
        #[Option(description: 'clear the cache before fetching so the data is fresh')]
        bool $refresh = false,
    ): void
    {
        $this->loadExisting();

        $downloadUrl = $this->globalGivingService->getAllProjectsDownload();
        $downloadUrl = $this->globalGivingService->getAllOrganizationsDownload()['url'];


        // could use Symfony client here, only 18M for orgs
        if (!file_exists($fn = 'orgs.xml')) {
            $response = $this->httpClient->request('GET', $downloadUrl);
            $fileHandler = fopen($fn, 'w');
            foreach ($this->httpClient->stream($response) as $chunk) {
                fwrite($fileHandler, $chunk->getContent());
            }
        }
        // @todo: https://sabre.io/xml/reading/
//        dd($downloadUrl, filesize($fn), realpath($fn));

        $next = null; // to start with;
        do {
            $data = $this->globalGivingService->getAllProjects(['nextProjectId' => $next]);
//        $data = $this->globalGivingService->getFeaturedProjects();
            foreach ($data['project'] as $projectData) {
                $org = $this->addObject(Organization::class, $orgData = $projectData['organization']);
                $projectData['organization'] = $org;
                $project = $this->addObject(Project::class, $projectData);
//            dd($projectData, $orgData, $project, $org);
            }
            $this->entityManager->flush();
            $next = $data['nextProjectId']??false;
//            dd($next);
        } while ($next);
        $io->success('app:load-data success.');
    }

    /**
     * @param array $data
     * @param mixed $objectOrArray
     * @return mixed
     */
    public function populateObject(array $data, object $objectOrArray): mixed
    {
        foreach ($data as $var => $val) {
            if ($this->propertyAccessor->isWritable($objectOrArray, $var)) {
                $this->propertyAccessor->setValue($objectOrArray, $var, $val);
            }
        }
        return $objectOrArray;
    }

    /**
     * @param $themes
     * @return array
     */
    public function getThemes($themes): array
    {
        $themeIds = [];
        foreach ($themes['theme'] as $themeData) {
            if (!$theme = $this->existingObjects[Theme::class][$themeData['id']] ?? null) {
                $theme = (new Theme())
                    ->setCode($code = $themeData['id'])
                    ->setLabel($themeData['name']);
                $this->existingObjects[Theme::class][$code] = $theme;
                $this->entityManager->persist($theme);
            }
            $themeIds[] = $theme->getCode();
        }
        return $themeIds;
        // now themes is just an array of ids
    }

    public function getCountryCodes($countries): array
    {
        $countryCodes = [];
        foreach ($countries['country'] as $country) {
            $countryCodes[] = $country['iso3166CountryCode'];
        }
        return $countryCodes;
        // now themes is just an array of ids
    }

    private function loadExisting()
    {
        foreach ([Organization::class, Project::class, Theme::class] as $objectClass) {
            foreach ($this->entityManager->getRepository($objectClass)->findAll() as $object) {
                $this->existingObjects[$objectClass][$object->getId()] = $object;
            }
        }
    }
    private function addObject(string $objectClass, array $data): Organization|Project
    {
        $id = $data['id'];
        /** @var $object Project|Organization */
        if (!$object = $this->existingObjects[$objectClass][$id]??null) {
            $object = (new $objectClass($id));
            $this->entityManager->persist($object);
            $this->existingObjects[$objectClass][$id] = $object;
        }
        $themes = $this->getThemes($data['themes']);
        $data['themes'] = array_unique($themes);
        $data['countries'] = $this->getCountryCodes($data['countries']);

        return $this->populateObject($data, $object);
    }
}
