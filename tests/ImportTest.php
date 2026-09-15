<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Project;
use App\Entity\Organization;
use App\Entity\Theme;
use App\Service\AppService;
use App\Service\SnapshotService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Survos\GlobalGiving\Enum\Export;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

final class ImportTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private string $directory;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool = new SchemaTool($this->em);
        $tool->dropSchema($metadata); $tool->createSchema($metadata);
        $this->directory = sys_get_temp_dir().'/gg-test-'.bin2hex(random_bytes(8));
        (new Filesystem())->mkdir($this->directory);
    }
    protected function tearDown(): void { (new Filesystem())->remove($this->directory); parent::tearDown(); }

    private function snapshot(string $xml): string
    {
        $file = $this->directory.'/projects.xml'; (new Filesystem())->dumpFile($file, $xml);
        return self::getContainer()->get(SnapshotService::class)->prepare(Export::Projects, true, $file)['path'];
    }

    public function testRepeatImportPreservesSourceIdentityAndHistoricalNullOrganization(): void
    {
        $path = $this->snapshot('<projects numberFound="2"><project><id>2</id><title>Old</title><active>false</active></project><project><id>90</id><title>Learning</title><active>true</active><organization><id>8</id><name>Education org</name></organization><themes><theme><id>edu</id><name>Education</name></theme></themes></project></projects>');
        $service = self::getContainer()->get(AppService::class);
        self::assertSame(2, $service->importSnapshot($path, Export::Projects));
        self::assertSame(2, $service->importSnapshot($path, Export::Projects));
        self::assertSame(2, $this->em->getRepository(Project::class)->count([]));
        self::assertSame(1, $this->em->getRepository(Organization::class)->count([]));
        self::assertSame(1, $this->em->getRepository(Theme::class)->count([]));
        self::assertNull($this->em->find(Project::class, 2)->organization);
        self::assertSame(8, $this->em->find(Project::class, 90)->organization->id);
        self::assertSame(['projectId' => 90], $this->em->find(Project::class, 90)->getRp());
    }

    public function testLimitedImportCannotRetireUnseenProjectsButFullImportCan(): void
    {
        $service = self::getContainer()->get(AppService::class);
        $first = $this->snapshot('<projects numberFound="2"><project><id>1</id><title>One</title><active>true</active></project><project><id>2</id><title>Two</title><active>true</active></project></projects>');
        $service->importSnapshot($first, Export::Projects);
        $second = $this->snapshot('<projects numberFound="1"><project><id>1</id><title>Changed</title><active>true</active></project></projects>');
        $service->importSnapshot($second, Export::ActiveProjects, 1);
        self::assertTrue($this->em->find(Project::class, 2)->active);
        $service->importSnapshot($second, Export::ActiveProjects);
        self::assertFalse($this->em->find(Project::class, 2)->active);
        self::assertSame('Changed', $this->em->find(Project::class, 1)->title);
    }
    public function testLocalSnapshotDoesNotReplaceRemotePointer(): void
    {
        $manifest = self::getContainer()->getParameter('kernel.project_dir').'/var/test-exports/projects/current.json';
        (new Filesystem())->dumpFile($manifest, '{"sentinel":true}');
        $this->snapshot('<projects numberFound="1"><project><id>3</id><title>Local</title><active>true</active></project></projects>');
        self::assertSame('{"sentinel":true}', file_get_contents($manifest));
        (new Filesystem())->remove($manifest);
    }

    public function testCountMismatchRollsBackImportedRows(): void
    {
        $path = $this->snapshot('<projects numberFound="1"><project><id>3</id><title>Local</title><active>true</active></project></projects>');
        try {
            self::getContainer()->get(AppService::class)->importSnapshot($path, Export::Projects, expectedCount: 2);
            self::fail('A truncated snapshot must fail.');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('record count mismatch', $e->getMessage());
        }
        self::assertSame(0, $this->em->getRepository(Project::class)->count([]));
    }

    public function testChecksumMismatchRejectsSnapshotBeforeImport(): void
    {
        $path = $this->snapshot('<projects numberFound="1"><project><id>3</id><title>Local</title><active>true</active></project></projects>');
        try {
            self::getContainer()->get(AppService::class)->importSnapshot($path, Export::Projects, sha256: str_repeat('0', 64));
            self::fail('A modified snapshot must fail.');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('checksum mismatch', $e->getMessage());
        }
        self::assertSame(0, $this->em->getRepository(Project::class)->count([]));
    }

}
