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
            $module['type'] = $this->slugify($module['_type'] ?? '');
            return $module;
        }, $pageModules);

        // Process modules for localization and HTML conversion
        $modulesArray = $this->processModulesRecursively($modulesArray, $language);

        $page['modules'] = $modulesArray;
        return $page;
    }

    /**
     * Recursively processes modules for localization and HTML block conversion.
     *
     * @param array $modules
     * @param string|null $language
     * @return array
     */
    private function processModulesRecursively(array $modules, ?string $language): array
    {
        foreach ($modules as $key => $module) {
            if (is_array($module)) {
                if (isset($module['type']) && $module['type'] === 'localeblockcontent') {
                    $modules[$key] = $this->processHtmlBlockModule($module);
                } else {
                    $modules[$key] = $this->processModulesRecursively($module, $language);
                }

                if ($language) {
                    $modules[$key] = $this->localizeModule($modules[$key], $language);
                }
            }
        }
        return $modules;
    }

    /**
     * Processes a module that contains (possibly nested) HTML block content.
     *
     * @param array $module
     * @return array
     */
    private function processHtmlBlockModule(array $module): array
    {
        $html = $this->convertBlocksToHtml($module);
        $module = ['text' => $html, 'type' => 'text']; // simplify the module
        return $module;
    }

    /**
     * Recursively searches data for nested HTML blocks and processes them.
     *
     * @param array $data
     * @return string
     */
    private function convertBlocksToHtml(array $data): string
    {
        $html = '';

        foreach ($data as $item) {
            if (is_array($item)) {
                if (isset($item['_type']) && $item['_type'] === 'block') {
                    $html .= BlockContent::toHtml($item);
                } else {
                    $html .= $this->convertBlocksToHtml($item);
                }
            }
        }

        return $html;
    }

    /**
     * Recursively localizes a module.
     *
     * @param array $module
     * @param string $language
     * @return array
     */
    private function localizeModule(array $module, string $language): array
    {
        foreach ($module as $key => $value) {
            if (is_array($value) && isset($value['_type']) && $value['_type'] === 'localeString') {
                if (isset($value[$language])) {
                    $module[$key] = $value[$language];
                }
            } elseif (is_array($value)) {
                $module[$key] = $this->localizeModule($value, $language);
            }
        }
        return $module;
    }

    /**
     * Converts text into a URL-friendly slug using underscores.
     */
    private function slugify(string $text): string
    {
        // Replace non-letter or digit characters with underscores
        $text = preg_replace('~[^\pL\d]+~u', '_', $text);
        // Transliterate to ASCII
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        // Remove unwanted characters
        $text = preg_replace('~[^_\w]+~', '', $text);
        // Trim and remove duplicate underscores
        $text = preg_replace('~_+~', '_', trim($text, '_'));
        // Lowercase the result
        return $text !== '' ? strtolower($text) : 'n_a';
    }
}