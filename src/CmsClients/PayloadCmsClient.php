<?php

namespace App\CmsClients;

use GuzzleHttp\Client;
use App\Services\CacheService;
use App\Utils\ApiFetcher;

class PayloadCmsClient implements CmsClientInterface {
    private string $apiUrl;
    private ApiFetcher $apiFetcher;

    public function __construct(string $apiUrl, CacheService $cache) {
        $this->apiUrl = rtrim($apiUrl, '/');
        $client = new Client([
            'base_uri' => $this->apiUrl,
            'timeout'  => 10.0,
        ]);
        $this->apiFetcher = new ApiFetcher($client, $cache);
    }

    public function getPages(): array {
        $url = $this->apiUrl . '/cms/api/pages';
        $response = $this->apiFetcher->fetch($url);
        return $response['docs'] ?? [];
    }

    public function getPage(string $slug, ?string $language = null): ?array {
        $page = $this->getPageBySlug($slug, $language);
        if (!$page || !isset($page['id'])) {
            return null;
        }
        return $this->getPageById($page['id'], $language);
    }

    public function getGlobal(string $global): ?array {
        $url = $this->apiUrl . '/globals/' . $global;
        return $this->apiFetcher->fetch($url);
    }

    private function getPageBySlug(string $slug, ?string $language = null): ?array {
        $query = [];
        if ($language) {
            $locale = $this->mapLanguageToLocale($language);
            $query['locale'] = $locale;
        }
        $url = $this->apiUrl . "/pages";
        $response = $this->apiFetcher->fetch($url);
        foreach ($response['docs'] as $doc) {
            if ($doc['slug'] === $slug) {
                return $doc;
            }
        }
        return null;
    }

    private function getPageById(string $id, ?string $language = null): ?array {
        $url = $this->apiUrl . '/pages/' . urlencode($id);
        if ($language) {
            $locale = $this->mapLanguageToLocale($language);
            $url .= '?locale=' . urlencode($locale);
        }
        return $this->apiFetcher->fetch($url);
    }

    private function mapLanguageToLocale(string $language): string {
        $locales = [
            'en' => 'en-US',
            'pl' => 'pl-PL',
        ];
        return $locales[$language] ?? 'en-US';
    }
}