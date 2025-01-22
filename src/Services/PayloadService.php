<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class PayloadService {
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

    /**
     * Get all pages.
     */
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

    /**
     * Get a page by slug.
     */
    public function getPage(string $slug, ?string $language = null): ?array {

        $cacheKey = 'page_' . $slug . ($language ? '_' . $language : '');

        return $this->cache->get($cacheKey, function() use ($slug, $language) {
            // Step 1: Resolve slug to ID
            $page = $this->getPageBySlug($slug, $language);
            if (!$page || !isset($page['id'])) {
                return null;
            }

            // Step 2: Fetch page details using ID
            return $this->getPageById($page['id']);
        });
    }

    /**
     * Resolve slug to page data (including ID).
     */
    private function getPageBySlug(string $slug, ?string $language = null): ?array {
        return $this->cache->get("page_slug_{$slug}", function() use ($slug, $language) {

            $query = [];
            if ($language) {
                $query['locale'] = $this->mapLanguageToLocale($language);
            }

            $response = $this->client->get("/cms/api/pages", [
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

    /**
     * Get a page by its ID.
     */
    private function getPageById(string $id): ?array {
        return $this->cache->get("page_id_{$id}", function() use ($id) {
            try {
                $response = $this->client->get("/cms/api/pages/{$id}");
                return json_decode($response->getBody(), true);
            } catch (RequestException $e) {
                error_log("Failed to fetch page with ID '{$id}': " . $e->getMessage());
                return null;
            }
        });
    }

    private function mapLanguageToLocale(string $language): string {
        $locales = [
            'en' => 'en-US',
            'pl' => 'pl-PL',
        ];

        return $locales[$language] ?? 'en-US';
    }
}