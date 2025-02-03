<?php

namespace App\CmsClients;

use App\Utils\ApiFetcher;

class SanityCmsClient implements CmsClientInterface
{
    private string $apiUrl;
    private ApiFetcher $apiFetcher;

    public function __construct(string $apiUrl)
    {
        $this->apiUrl = rtrim($apiUrl, '/');
        $this->apiFetcher = new ApiFetcher($this->apiUrl);
    }

    public function getPages(): array
    {
        $url = $this->apiUrl . '/data/query/production?query=*[_type == "page"]';
        $response = $this->apiFetcher->fetchFromApi($url);
        return $response['result'] ?? [];
    }

    public function getPage(string $slug, ?string $language = null): ?array
    {
        $query = '*[_type == "page" && slug.current == "' . $slug . '"][0]';
        $url = $this->apiUrl . '/data/query/production?query=' . urlencode($query);
        $response = $this->apiFetcher->fetchFromApi($url);
        return $this->formatPage($response);
    }

    public function getScaffold(string $global): ?array
    {
        $query = '*[_type == "' . $global . '"][0]';
        $url = $this->apiUrl . '/data/query/production?query=' . urlencode($query);
        $response = $this->apiFetcher->fetchFromApi($url);
        return $response['result'] ?? null;
    }

    public function getCollectionItem(string $collection, string $slug, ?string $language = null): ?array
    {
        $query = '*[_type == "' . $collection . '" && slug.current == "' . $slug . '"][0]';
        $url = $this->apiUrl . '/data/query/production?query=' . urlencode($query);
        $response = $this->apiFetcher->fetchFromApi($url);
        return $response['result'] ?? null;
    }

    public function formatPage($page)
    {
        if (empty($page['result'])) {
            return null;
        }

        $pageModules = $page['result']['pageBuilder'] ?? [];

        $modulesArray = [];

        if ($pageModules) {
            foreach ($pageModules as $key => $module) {
                $module['type'] =  $this->slugify($module['_type']);
                $modulesArray[] = $module;
            }
        }

        $page['modules'] = $modulesArray;
        return $page;
    }

    private function slugify(string $text): string
    {
        // Replace non-letter or digits by _
        $text = preg_replace('~[^\pL\d]+~u', '_', $text);

        // Transliterate
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);

        // Remove unwanted characters
        $text = preg_replace('~[^_\w]+~', '', $text);

        // Trim
        $text = trim($text, '_');

        // Remove duplicate _
        $text = preg_replace('~_+~', '_', $text);

        // Lowercase
        $text = strtolower($text);

        if (empty($text)) {
            return 'n_a';
        }

        return $text;
    }
}
