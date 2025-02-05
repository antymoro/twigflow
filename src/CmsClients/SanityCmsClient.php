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

        // Process the data recursively for both HTML conversion and localization
        $modulesArray = $this->processDataRecursively($pageModules, $language);

        // Set a "type" key based on _type for consistency
        $modulesArray = array_map(function ($module) {
            $module['type'] = $this->slugify($module['_type'] ?? '');
            return $module;
        }, $modulesArray);

        $page['modules'] = $modulesArray;
        return $page;
    }

    /**
     * Public method to process any data set for localization and HTML block conversion.
     *
     * @param array $data
     * @param string|null $language
     * @return array
     */
    public function processData(array $data, ?string $language = null): array
    {
        return $this->processDataRecursively($data, $language);
    }

    /**
     * Recursively processes an arbitrary data structure.
     * - If an array has key "type" equal to "localeblockcontent", 
     *   it will be replaced by its parsed HTML in a "text" field.
     * - If an array has _type "localeString", it will be localized.
     * - Otherwise, it recurses through all keys.
     *
     * @param mixed $data
     * @param string|null $language
     * @return mixed
     */
    private function processDataRecursively($data, ?string $language)
    {
        if (is_array($data)) {
            // Check if this array represents a localized string
            if (isset($data['_type']) && $data['_type'] === 'localeString') {
                if ($language && isset($data[$language])) {
                    return $data[$language];
                }
                return $data;
            }
            // Check if this array is a special HTML block container
            if (isset($data['_type']) && $data['_type'] === 'localeBlockContent') {
                return $this->processHtmlBlockModule($data);
            }
            // Otherwise, recursively process each element
            $result = [];
            foreach ($data as $key => $value) {
                $result[$key] = $this->processDataRecursively($value, $language);
            }
            return $result;
        }
        return $data; // For scalar values, just return as is.
    }

    /**
     * Processes an array assumed to be a HTML block container.
     * Converts all nested blocks to HTML and returns an array with only "text" and "type" keys.
     *
     * @param array $module
     * @return array
     */
    private function processHtmlBlockModule(array $module): array
    {
        $html = $this->convertBlocksToHtml($module);
        return ['text' => $html, 'type' => 'text', '_type' => 'text'];
    }

    /**
     * Recursively converts nested blocks to HTML.
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
