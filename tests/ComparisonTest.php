<?php

declare(strict_types=1);

namespace App\Tests;

use App\Lab\LabGateway;
use App\Lab\Ranking;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ComparisonTest extends TestCase
{
    public function testFusionRewardsAgreementWithoutComparingEngineScores(): void
    {
        $keyword = [['id' => 1, 'score' => 9999], ['id' => 2, 'score' => 1]];
        $vector = [['id' => 3, 'score' => 0.9], ['id' => 2, 'score' => 0.8]];
        self::assertSame([2, 1, 3], array_column(Ranking::fuse($keyword, $vector), 'id'));
        self::assertSame([2], array_column(Ranking::fuse($keyword, $vector, 1), 'id'));
    }

    public function testVectorQueriesUseSameVectorAndCountryPrefilter(): void
    {
        $requests = [];
        $http = new MockHttpClient(function ($method, $url, $options) use (&$requests) {
            $requests[] = json_decode($options['body'], true, flags: JSON_THROW_ON_ERROR);
            return new MockResponse(str_contains($url, '/_search') ? '{"hits":{"hits":[{"_source":{"id":7}}]}}' : '{"hits":[{"id":7}]}');
        });
        $gateway = new LabGateway($http, 'http://elastic', '', 'http://meili', '', 'http://ollama/api/embed', 'model');
        self::assertSame([['id' => 7]], $gateway->search('elastic', 'sample', 'water', [0.1, 0.2], 'GT'));
        self::assertSame([['id' => 7]], $gateway->search('meili', 'sample', 'water', [0.1, 0.2], 'GT'));
        self::assertSame($requests[0]['knn']['query_vector'], $requests[1]['vector']);
        self::assertSame('GT', $requests[0]['knn']['filter']['bool']['filter'][0]['term']['countries']);
        self::assertSame('countries = "GT"', $requests[1]['filter']);
        self::assertSame(1.0, (float) $requests[1]['hybrid']['semanticRatio']);
    }

    public function testTransportErrorsDoNotExposeCredentialOrResponseBody(): void
    {
        $gateway = new LabGateway(new MockHttpClient(new MockResponse('sensitive response', ['http_code' => 401])), 'http://elastic', 'secret-key', '', '', '', 'model');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Elastic request failed. Check connectivity, credentials and index readiness.');
        $gateway->request('elastic', 'GET', 'sample/_count');
    }

    public function testEmptyEmbeddingFailsBeforeIndexing(): void
    {
        $gateway = new LabGateway(new MockHttpClient(new MockResponse('{"embeddings":[[]]}')), '', '', '', '', 'http://ollama/api/embed', 'model');
        $this->expectException(\RuntimeException::class);
        $gateway->embed('water');
    }
    public function testJudgmentsAreSharedAcrossEnginesAndModesButNotSnapshots(): void
    {
        $directory = sys_get_temp_dir().'/gg-comparison-'.bin2hex(random_bytes(8));
        $fs = new \Symfony\Component\Filesystem\Filesystem();
        $gateway = new LabGateway(new MockHttpClient(), '', '', '', '', '', 'model');
        $lab = new \App\Service\ComparisonService($gateway, $this->createStub(\Doctrine\ORM\EntityManagerInterface::class), $fs, new \Symfony\Component\Lock\LockFactory(new \Symfony\Component\Lock\Store\InMemoryStore()), $directory);
        try {
            $run = ['query' => 'school', 'country' => 'IN', 'mode' => 'keyword', 'snapshot' => 'first', 'results' => ['meili' => ['hits' => [['id' => 7]]], 'elastic' => ['hits' => [['id' => 7]]]]];
            $id = $lab->saveRun($run);
            self::assertNull($lab->judgments($run)[7]);
            $lab->judge($id, 7, 0);
            $run['mode'] = 'hybrid';
            self::assertSame(0, $lab->judgments($run)[7]);
            $run['snapshot'] = 'second';
            self::assertNull($lab->judgments($run)[7]);
            $lab->judge($id, 7, -1);
            self::assertNull($lab->judgments($lab->run($id))[7]);
            $this->expectException(\InvalidArgumentException::class);
            $lab->judge($id, 999, 2);
        } finally { $fs->remove($directory); }
    }
    public function testCountMismatchDoesNotPublishAnIncompleteIndex(): void
    {
        $directory = sys_get_temp_dir().'/gg-comparison-'.bin2hex(random_bytes(8));
        $fs = new \Symfony\Component\Filesystem\Filesystem(); $fs->mkdir($directory);
        $writer = \Survos\JsonlBundle\IO\JsonlWriter::open($directory.'/snapshot.jsonl');
        $writer->write(['id' => 7, 'text' => 'school', 'vector' => [0.1, 0.2]]); $writer->close();
        $fs->dumpFile($directory.'/current.json', json_encode(['snapshot' => hash_file('sha256', $directory.'/snapshot.jsonl'), 'records' => 'snapshot.jsonl', 'dimensions' => 2, 'count' => 1], JSON_THROW_ON_ERROR));
        $fs->dumpFile($directory.'/elastic.json', '{"index":"previous-valid-index"}');
        $http = new MockHttpClient(static fn ($method, $url) => new MockResponse(str_ends_with($url, '/_count') ? '{"count":0}' : '{}'));
        $gateway = new LabGateway($http, 'http://elastic', '', '', '', '', 'model');
        $lab = new \App\Service\ComparisonService($gateway, $this->createStub(\Doctrine\ORM\EntityManagerInterface::class), $fs, new \Symfony\Component\Lock\LockFactory(new \Symfony\Component\Lock\Store\InMemoryStore()), $directory);
        try {
            $io = new \Symfony\Component\Console\Style\SymfonyStyle(new \Symfony\Component\Console\Input\ArrayInput([]), new \Symfony\Component\Console\Output\BufferedOutput());
            try { $lab->index($io, 'elastic'); self::fail('Incomplete index must not publish.'); }
            catch (\RuntimeException $e) { self::assertSame('Index count does not match snapshot.', $e->getMessage()); }
            self::assertSame('{"index":"previous-valid-index"}', file_get_contents($directory.'/elastic.json'));
        } finally { $fs->remove($directory); }
    }
}
