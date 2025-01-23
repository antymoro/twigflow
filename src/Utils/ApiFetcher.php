<?php

namespace App\Utils;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use App\Services\CacheService;

class ApiFetcher {
    private CacheService $cache;
    private Client $client;

    public function __construct(Client $client, CacheService $cache) {
        $this->client = $client;
        $this->cache = $cache;
    }

    public function fetch(string $url): ?array {

        $cacheKey = $this->generateCacheKey($url);
        return $this->cache->get($cacheKey, function() use ($url) {
            try {
                $response = $this->client->get($url);
                return json_decode($response->getBody(), true);
            } catch (RequestException $e) {
                error_log("Failed to fetch data from '{$url}': " . $e->getMessage());
                return null;
            }
        });
    }

    private function generateCacheKey(string $url): string {
        return 'cache_' . md5($url);
    }
}