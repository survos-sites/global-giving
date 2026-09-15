<?php

declare(strict_types=1);

namespace App\Tests;

use App\Research\Ein;
use App\Research\ProPublicaClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ProPublicaClientTest extends TestCase
{
    public function testLeadingZeroEinAndLatestFilingArePreserved(): void
    {
        $http = new MockHttpClient([new MockResponse(json_encode(['organization' => ['ein' => 12345678, 'name' => 'Example'], 'filings_with_data' => [['tax_prd' => 202012, 'tax_prd_yr' => 2020], ['tax_prd' => 202412, 'tax_prd_yr' => 2024, 'totrevenue' => 0]]], JSON_THROW_ON_ERROR))]);
        $client = new ProPublicaClient($http, new ArrayAdapter()); $result = $client->lookup(Ein::fromString('01-2345678'));
        self::assertSame('012345678', $result['ein']); self::assertSame(2024, $result['latestFiling']['year']); self::assertSame(0, $result['latestFiling']['revenue']);
        self::assertSame($result, $client->lookup(Ein::fromString('012345678'))); self::assertSame(1, $http->getRequestsCount());
    }
    public function testDifferentEinCannotBecomeAMatch(): void
    {
        $client = new ProPublicaClient(new MockHttpClient([new MockResponse('{"organization":{"ein":987654321,"name":"Wrong"}}')]), new ArrayAdapter());
        $this->expectException(\RuntimeException::class); $client->lookup(new Ein('123456789'));
    }
    public function testNotFoundIsNotAnErrorOrANameMatch(): void
    {
        $client = new ProPublicaClient(new MockHttpClient([new MockResponse('{}', ['http_code' => 404])]), new ArrayAdapter());
        self::assertNull($client->lookup(new Ein('123456789')));
    }
}
