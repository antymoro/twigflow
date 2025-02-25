<?php

namespace App\Utils;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Create;
use App\Services\CacheService;

class ApiFetcher
{
    private CacheService $cache;
    private Client $client;
    private string $apiUrl;

    public function __construct(string $baseUri)
    {
        $this->apiUrl = rtrim($baseUri, '/');

        $apiKey = $_ENV['API_KEY'] ?? null;
        $headers = [];
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

    public function fetchFromApi(string $query, array $options = []): ?array
    {
        // $url = $this->apiUrl . urlencode($query);
        $url = $this->apiUrl . $query;
        $cacheKey = $this->generateCacheKey($url);

        if (isset($options['disable_cache']) && $options['disable_cache'] === true) {
            $response = $this->client->get($url);
            return json_decode($response->getBody(), true);
        }

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

    public function asyncFetchFromApi(string $query, array $options = []): PromiseInterface
    {
        // $url = $this->apiUrl . urlencode($query);
        $url = $this->apiUrl . $query;
        return $this->asyncFetch($url);
    }

    public function asyncFetch(string $url): PromiseInterface
    {
        $cacheKey = $this->generateCacheKey($url);

        if (isset($options['disable_cache']) && $options['disable_cache'] === true) {
            return $this->client->getAsync($url);
        }

        $existingValue = $this->cache->get($cacheKey, fn() => false);

        if ($existingValue !== false) {
            return Create::promiseFor($existingValue);
        } else {
            return $this->client->getAsync($url)->then(function ($response) use ($cacheKey) {
                $data = json_decode($response->getBody()->getContents(), true);
                $this->cache->set($cacheKey, $data);
                return $data;
            });
        }
    }

    private function generateCacheKey(string $url): string
    {
        return 'cache_' . md5($url);
    }
}