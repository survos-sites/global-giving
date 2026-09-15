<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class WebTest extends WebTestCase
{
    public function testCatalogPagesResolveNaturalIdentityAndApiIsReadOnly(): void
    {
        $client = self::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $tool = new SchemaTool($em); $tool->dropSchema($metadata); $tool->createSchema($metadata);
        $project = new Project(4242); $project->title = 'Clean water for a community'; $project->active = true;
        $project->summary = 'Community-led water access'; $project->importedAt = new \DateTimeImmutable(); $project->source = ['privateSourceTest' => 'not in public API'];
        $em->persist($project); $em->flush();
        $client->request('GET', '/'); self::assertResponseIsSuccessful(); self::assertSelectorTextContains('h1', 'changing their world');
        $client->request('GET', '/project/4242'); self::assertResponseIsSuccessful(); self::assertSelectorTextContains('h1', 'Clean water');
        $client->request('GET', '/project/9999999'); self::assertResponseStatusCodeSame(404);
        $client->request('GET', '/api/projects/4242.json'); self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(4242, $data['id']); self::assertArrayNotHasKey('source', $data);
        $client->request('POST', '/api/projects.json', server: ['CONTENT_TYPE' => 'application/json'], content: '{}'); self::assertResponseStatusCodeSame(405);
        $client->request('GET', '/projects'); self::assertResponseIsSuccessful(); self::assertSelectorExists('[data-controller="survos--meili-bundle--insta"]');
    }
}
