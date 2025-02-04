<?php

namespace App\CmsClients;

use App\Utils\ApiFetcher;
use Sanity\BlockContent;

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
        $response = $this->fetchQuery('*[_type == "page"]');
        return $response['result'] ?? [];
    }

    public function getPage(string $slug, ?string $language = null): ?array
    {
        $query = '*[_type == "page" && slug.current == "' . $slug . '"][0]';
        $response = $this->fetchQuery($query);
        return $this->formatPage($response, $language);
    }

    public function getScaffold(string $global): ?array
    {
        $query = '*[_type == "' . $global . '"][0]';
        $response = $this->fetchQuery($query);
        return $response['result'] ?? null;
    }

    public function getCollectionItem(string $collection, string $slug, ?string $language = null): ?array
    {
        $query = '*[_type == "' . $collection . '" && slug.current == "' . $slug . '"][0]';
        $response = $this->fetchQuery($query);
        return $this->formatPage($response, $language);
    }

    /**
     * Helper method to build the URL with query and fetch the API response.
     */
    private function fetchQuery(string $query): ?array
    {
        $url = $this->apiUrl . '/data/query/production?query=' . urlencode($query);
        return $this->apiFetcher->fetchFromApi($url);
    }

    /**
     * Formats the API response page.
     */
    public function formatPage($page, ?string $language = null): ?array
    {
        if (empty($page['result'])) {
            return null;
        }

        $pageModules = $page['result']['modules'] ?? [];
        

        $modulesArray = array_map(function ($module) {
            $module['type'] = slugify($module['_type'] ?? '');
            return $module;
        }, $pageModules);

        if ($language) {
            $modulesArray = $this->filterModulesByLanguage($modulesArray, $language);
        }

        // Process modules that require HTML conversion
        foreach ($modulesArray as $key => $module) {
            if ($module['type'] === 'localeblockcontent') {
                $html = '';

                foreach ($module as $content) {
                    if (is_array($content) && isset($content['_type']) && $content['_type'] === 'block') {
                        $html .= BlockContent::toHtml($content);
                    }
                }

                $modulesArray[$key]['parsed_html'] = $html;
                $modulesArray[$key]['type'] = 'text';
            }
        }

        $page['modules'] = $modulesArray;
        return $page;
    }

    /**
     * Filters modules by language while preserving the module type.
     */
    private function filterModulesByLanguage(array $modules, string $language): array
    {
        return array_map(function ($module) use ($language) {
            if (isset($module[$language]) && is_array($module[$language])) {
                $type = $module['type'] ?? '';
                $module = $module[$language];
                $module['type'] = $type;
            }
            return $module;
        }, $modules);
    }

}