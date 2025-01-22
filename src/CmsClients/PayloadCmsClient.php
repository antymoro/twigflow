<?php

namespace App\CmsClients;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use App\Services\CacheService;

class PayloadCmsClient implements CmsClientInterface {
    private string $apiUrl;
    private Client $client;
    private CacheService $cache;

    public function __construct(string $apiUrl, CacheService $cache) {
        $this->apiUrl = rtrim($apiUrl, '/');
        $this->client = new Client([
            'base_uri' => $this->apiUrl,
            'timeout'  => 10.0,
        ]);
        $this->cache = $cache;
    }

    public function getPages(): array {
        return $this->cache->get('pages', function() {
            try {
                $response = $this->client->get("/cms/api/pages");
                $data = json_decode($response->getBody(), true);
                return $data['docs'] ?? [];
            } catch (RequestException $e) {
                error_log("Failed to fetch pages: " . $e->getMessage());
                return [];
            }
        });
    }

    public function getPage(string $slug, ?string $language = null): ?array {
        $cacheKey = 'page_' . $slug . ($language ? '_' . $language : '');

        return $this->cache->get($cacheKey, function() use ($slug, $language) {
            $page = $this->getPageBySlug($slug, $language);
            if (!$page || !isset($page['id'])) {
                return null;
            }
            return $this->getPageById($page['id'], $language);
        });
    }

    private function getPageBySlug(string $slug, ?string $language = null): ?array {
        $cacheKey = 'page_slug_' . $slug . ($language ? '_' . $language : '');

        return $this->cache->get($cacheKey, function() use ($slug, $language) {
            $query = [];
            if ($language) {
                $locale = $this->mapLanguageToLocale($language);
                $query['locale'] = $locale;
            }

            $response = $this->client->get($this->apiUrl . "/pages", [
                'query' => $query
            ]);

            $data = json_decode($response->getBody(), true);

            foreach ($data['docs'] as $doc) {
                if ($doc['slug'] === $slug) {
                    return $doc;
                }
            }
            return null;
        });
    }

    private function getPageById(string $id, ?string $language = null): ?array {
        $url = $this->apiUrl . '/pages/' . urlencode($id);
        if ($language) {
            $locale = $this->mapLanguageToLocale($language);
            $url .= '?locale=' . urlencode($locale);
        }

        try {
            $response = $this->client->get($url);
            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            error_log("Failed to fetch page by ID: " . $e->getMessage());
            return null;
        }
    }

    private function mapLanguageToLocale(string $language): string {
        $locales = [
            'en' => 'en-US',
            'pl' => 'pl-PL',
        ];

        return $locales[$language] ?? 'en-US';
    }
}