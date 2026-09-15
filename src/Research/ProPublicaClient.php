<?php

declare(strict_types=1);

namespace App\Research;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class ProPublicaClient
{
    public function __construct(private HttpClientInterface $httpClient, private CacheInterface $cache) {}

    /** @return array{name:string, ein:string, nteeCode:?string, sourceUrl:string, latestFiling:?array, source:array}|null */
    public function lookup(Ein $ein): ?array
    {
        return $this->cache->get('propublica.v2.'.$ein->value, function(ItemInterface $item) use ($ein): ?array {
            $item->expiresAfter(86400);
            $response = $this->httpClient->request('GET', 'https://projects.propublica.org/nonprofits/api/v2/organizations/'.$ein->value.'.json', ['timeout' => 30]);
            if ($response->getStatusCode() === 404) { return null; }
            $data = $response->toArray();
            $org = $data['organization'] ?? throw new \RuntimeException('ProPublica response has no organization.');
            $returnedEin = str_pad((string) ($org['ein'] ?? ''), 9, '0', STR_PAD_LEFT);
            if ($returnedEin !== $ein->value) { throw new \RuntimeException('ProPublica returned a different EIN; no match stored.'); }
            $filings = $data['filings_with_data'] ?? [];
            usort($filings, static fn(array $a, array $b): int => ($b['tax_prd'] ?? 0) <=> ($a['tax_prd'] ?? 0));
            $latest = $filings[0] ?? null;
            return [
                'name' => $org['name'], 'ein' => $returnedEin, 'nteeCode' => $org['ntee_code'] ?? null,
                'sourceUrl' => 'https://projects.propublica.org/nonprofits/organizations/'.$ein->value,
                'latestFiling' => $latest ? [
                    'year' => $latest['tax_prd_yr'] ?? null,
                    'revenue' => $latest['totrevenue'] ?? null, 'expenses' => $latest['totfuncexpns'] ?? null,
                    'assets' => $latest['totassetsend'] ?? null,
                    'url' => isset($latest['pdf_url']) && parse_url($latest['pdf_url'], PHP_URL_SCHEME) === 'https' ? $latest['pdf_url'] : null,
                ] : null,
                // Preserve the provider's unmodified payload as provenance, not merged entity fields.
                'source' => $data,
            ];
        });
    }
}
