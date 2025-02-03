<?php

namespace App\Utils;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\PromiseInterface;
use App\Services\CacheService;

class ApiFetcher
{
    private CacheService $cache;
    private Client $client;

    public function __construct(string $baseUri)
    {
        $headers = [];

        $apiKey = $_ENV['API_KEY'] ?? null;

        if ($apiKey) {
            $headers['Authorization'] = 'Bearer ' . $apiKey;
        }

        $this->client = new Client([
            'base_uri' => rtrim($baseUri, '/'),
            'timeout'  => 5.0,
            'headers'  => $headers,
        ]);
        $this->cache = new CacheService();
    }

    public function fetchFromApi(string $url, array $options = []): ?array
    {
        if (isset($options['query'])) {
            $url .= '?' . http_build_query($options['query']);
        }
        $cacheKey = $this->generateCacheKey($url);
        return $this->cache->get($cacheKey, function () use ($url) {
            try {
                $response = $this->client->get($url);
                return json_decode($response->getBody(), true);
            } catch (RequestException $e) {
                error_log("Failed to fetch data from '{$url}': " . $e->getMessage());
                return null;
            }
        });
    }

    public function asyncFetchFromApi(string $url, array $options = []): PromiseInterface
    {
        if (isset($options['query'])) {
            $url .= '?' . http_build_query($options['query']);
        }
        return $this->client->getAsync($url);
    }

    private function generateCacheKey(string $url): string
    {
        return 'cache_' . md5($url);
    }
}