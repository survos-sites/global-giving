<?php

declare(strict_types=1);

namespace App\Lab;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class LabGateway
{
    public function __construct(
        #[Autowire(service: 'app.lab_http')] private HttpClientInterface $http,
        #[Autowire('%env(LAB_ES_URL)%')] private string $esUrl,
        #[Autowire('%env(LAB_ES_API_KEY)%')] private string $esKey,
        #[Autowire('%env(MEILI_SERVER)%')] private string $meiliUrl,
        #[Autowire('%env(MEILI_ADMIN_KEY)%')] private string $meiliKey,
        #[Autowire('%env(OLLAMA_EMBED_URL)%')] private string $embedUrl,
        #[Autowire('%env(OLLAMA_EMBED_MODEL)%')] public string $model,
    ) {}

    public function request(string $engine, string $method, string $path, ?array $body = null): array
    {
        $url = $engine === 'elastic' ? $this->esUrl : $this->meiliUrl;
        $key = $engine === 'elastic' ? $this->esKey : $this->meiliKey;
        if ($url === '') { throw new \RuntimeException('Elasticsearch is not configured yet. Set LAB_ES_URL.'); }
        $options = ['timeout' => 10, 'max_duration' => 30];
        if ($key !== '') { $options['headers']['Authorization'] = ($engine === 'elastic' ? 'ApiKey ' : 'Bearer ').$key; }
        if ($body !== null) { $options['json'] = $body; }
        try {
            $response = $this->http->request($method, rtrim($url, '/').'/'.ltrim($path, '/'), $options);
            if ($response->getStatusCode() >= 300) { throw new \RuntimeException(); }
            return $response->toArray();
        } catch (\Throwable) { throw new \RuntimeException(ucfirst($engine).' request failed. Check connectivity, credentials and index readiness.'); }
    }

    public function modelDigest(): string
    {
        $url = preg_replace('~/api/embed$~', '/api/tags', $this->embedUrl);
        $models = $this->http->request('GET', $url, ['timeout' => 10, 'max_duration' => 15])->toArray()['models'] ?? [];
        foreach ($models as $model) {
            if (in_array($model['name'], [$this->model, $this->model.':latest'], true)) { return $model['digest']; }
        }
        throw new \RuntimeException('Configured Ollama model is not installed.');
    }

    public function embed(string $text): array
    {
        $response = $this->http->request('POST', $this->embedUrl, ['json' => ['model' => $this->model, 'input' => $text, 'truncate' => false], 'timeout' => 60, 'max_duration' => 120])->toArray();
        $vector = $response['embeddings'][0] ?? [];
        if ($vector === [] || array_filter($vector, static fn ($v) => !is_numeric($v) || !is_finite((float) $v)) !== []) { throw new \RuntimeException('Ollama returned an invalid embedding.'); }
        return $vector;
    }

    public function wait(array $task): void
    {
        $deadline = microtime(true) + 120;
        do {
            $status = $this->request('meili', 'GET', 'tasks/'.$task['taskUid']);
            if ($status['status'] === 'succeeded') { return; }
            if (in_array($status['status'], ['failed', 'canceled'], true)) { throw new \RuntimeException('Meilisearch indexing task failed.'); }
            usleep(200000);
        } while (microtime(true) < $deadline);
        throw new \RuntimeException('Meilisearch indexing timed out.');
    }

    public function search(string $engine, string $index, string $query, ?array $vector, string $country): array
    {
        if ($engine === 'meili') {
            $body = ['q' => $query, 'limit' => 50, 'attributesToRetrieve' => ['id', 'title', 'summary', 'countries', 'themes']];
            if ($country !== '') { $body['filter'] = 'countries = '.json_encode($country, JSON_THROW_ON_ERROR); }
            if ($vector !== null) { $body['vector'] = $vector; $body['hybrid'] = ['embedder' => 'shared', 'semanticRatio' => 1.0]; }
            return $this->request($engine, 'POST', 'indexes/'.$index.'/search', $body)['hits'];
        }
        $filter = $country === '' ? [] : [['term' => ['countries' => $country]]];
        $body = ['size' => 50, '_source' => ['id', 'title', 'summary', 'countries', 'themes']];
        if ($vector !== null) {
            $body['knn'] = ['field' => 'vector', 'query_vector' => $vector, 'k' => 50, 'num_candidates' => 200];
            if ($filter !== []) { $body['knn']['filter'] = ['bool' => ['filter' => $filter]]; }
        } else { $body['query'] = ['bool' => ['must' => [['match' => ['text' => $query]]], 'filter' => $filter]]; }
        return array_column($this->request($engine, 'POST', $index.'/_search', $body)['hits']['hits'], '_source');
    }
}
