<?php

namespace App\Utils;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Create;
use App\Services\CacheService;
use App\CmsClients\CmsClientInterface;

class ApiFetcher
{
    private CacheService $cache;
    private Client $client;
    private string $apiUrl;
    private CmsClientInterface $cmsClient;

    public function __construct(string $baseUri, CmsClientInterface $cmsClient)
    {
        $this->apiUrl = rtrim($baseUri, '/');
        $this->cmsClient = $cmsClient;

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
        $url = $this->buildUrl($query, $options);
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
        $url = $this->buildUrl($query, $options);
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

    public function postToApi(array $requestBody): bool
    {

        $url = 'https://' . $_ENV['API_ID'] . '.api.sanity.io/v2022-03-07/data/mutate/' . $_ENV['API_ENV'];

        try {
            $response = $this->client->post($url, [
                'json' => $requestBody,
                'headers' => [
                    'Authorization' => 'Bearer ' .$_ENV['API_KEY'],
                ],
            ]);

            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            dd($e->getResponse()->getBody()->getContents());
            error_log("Failed to post documents to Sanity CMS: " . $e->getMessage());
            return false;
        }
    }

    private function buildUrl(string $query, array $options): string
    {
        return $this->cmsClient->urlBuilder($this->apiUrl, $query, $options);
    }

    private function generateCacheKey(string $url): string
    {
        return 'cache_' . md5($url);
    }
}