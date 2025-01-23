<?php

namespace App\CmsClients;

use App\Utils\ApiFetcher;

class PayloadCmsClient implements CmsClientInterface {
    private string $apiUrl;
    private ApiFetcher $apiFetcher;

    /**
     * Constructor to initialize dependencies.
     *
     * @param string $apiUrl
     * @param CacheService $cache
     */
    public function __construct(string $apiUrl) {
        $this->apiUrl = rtrim($apiUrl, '/');
        $this->apiFetcher = new ApiFetcher($this->apiUrl);
    }

    /**
     * Fetch all pages.
     *
     * @return array
     */
    public function getPages(): array {
        $url = $this->apiUrl . '/cms/api/pages';
        $response = $this->apiFetcher->fetchFromApi($url);
        return $response['docs'] ?? [];
    }

    /**
     * Fetch a page by slug and language.
     *
     * @param string $slug
     * @param string|null $language
     * @return array|null
     */
    public function getPage(string $slug, ?string $language = null): ?array {
        $page = $this->getPageBySlug($slug, $language);
        if (!$page || !isset($page['id'])) {
            return null;
        }
        return $this->getPageById($page['id'], $language);
    }

    /**
     * Fetch global data by key.
     *
     * @param string $global
     * @return array|null
     */
    public function getGlobal(string $global): ?array {
        $url = $this->apiUrl . '/globals/' . $global;
        return $this->apiFetcher->fetchFromApi($url);
    }

    /**
     * Fetch a page by slug.
     *
     * @param string $slug
     * @param string|null $language
     * @return array|null
     */
    private function getPageBySlug(string $slug, ?string $language = null): ?array {
        $query = ['slug' => $slug];
        if ($language) {
            $locale = $this->mapLanguageToLocale($language);
            $query['locale'] = $locale;
        }
        $url = $this->apiUrl . "/pages";
        $response = $this->apiFetcher->fetchFromApi($url, ['query' => $query]);
        foreach ($response['docs'] as $doc) {
            if ($doc['slug'] === $slug) {
                return $doc;
            }
        }
        return null;
    }

    /**
     * Fetch a page by ID.
     *
     * @param string $id
     * @param string|null $language
     * @return array|null
     */
    private function getPageById(string $id, ?string $language = null): ?array {
        $url = $this->apiUrl . '/pages/' . urlencode($id);
        if ($language) {
            $locale = $this->mapLanguageToLocale($language);
            $url .= '?locale=' . urlencode($locale);
        }
        return $this->apiFetcher->fetchFromApi($url);
    }

    /**
     * Map language code to locale.
     *
     * @param string $language
     * @return string
     */
    private function mapLanguageToLocale(string $language): string {
        $locales = [
            'en' => 'en-US',
            'pl' => 'pl-PL',
        ];
        return $locales[$language] ?? 'en-US';
    }
}