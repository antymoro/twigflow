<?php

namespace App\CmsClients;

use App\Utils\ApiFetcher;
use Sanity\BlockContent;

class SanityCmsClient implements CmsClientInterface
{
    private string $apiUrl;
    private ApiFetcher $apiFetcher;
    private array $referenceIds = [];

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

        // // Process the data recursively for both HTML conversion and localization
        // $modulesArray = $this->processData($pageModules, $language);

        // Set a "type" key based on _type for consistency
        $modulesArray = array_map(function ($module) {
            $module['type'] = $this->slugify($module['_type'] ?? '');
            return $module;
        }, $pageModules);

        $page['modules'] = $modulesArray;

        return $page;
    }

    /**
     * Public method to process any data set for localization, HTML block conversion, and reference resolution.
     *
     * @param array $data
     * @param string|null $language
     * @return array
     */
    public function processData(array $data, ?string $language = null): array
    {
        // First process localization, HTML conversion, and collect references
        $processed = $this->processDataRecursively($data, $language);

        // Fetch and cache all references
        $references = $this->fetchReferences();

        // Substitute references in the processed data
        return $this->substituteReferences($processed, $references);
    }

    /**
     * Recursively processes an arbitrary data structure.
     * - If an array has _type "localeString", it will be localized.
     * - If an array has _type "localeBlockContent", it converts nested blocks to HTML.
     * - If an array has _type "reference", it collects the reference ID.
     * - Otherwise, it recurses through all keys.
     *
     * @param mixed $data
     * @param string|null $language
     * @return mixed
     */
    private function processDataRecursively($data, ?string $language)
    {
        if (is_array($data)) {
            switch ($data['_type'] ?? null) {
                case 'localeString':
                    if ($language && isset($data[$language])) {
                        return $data[$language];
                    }
                    return $data;
                case 'localeBlockContent':
                    return $this->processHtmlBlockModule($data);
                case 'reference':
                    if (isset($data['_ref'])) {
                        $this->referenceIds[] = $data['_ref'];
                    }
                    return $data;
                default:
                    $result = [];
                    foreach ($data as $key => $value) {
                        $result[$key] = $this->processDataRecursively($value, $language);
                    }
                    return $result;
            }
        }
        return $data; // Return scalar values unchanged.
    }

    /**
     * Fetches all references collected during processing.
     *
     * @return array
     */
    private function fetchReferences(): array
    {
        if (empty($this->referenceIds)) {
            return [];
        }

        $refIds = array_values(array_unique($this->referenceIds));
        $refIdsString = '["' . implode('","', $refIds) . '"]';

        // Build GROQ query specifying the fields you need (e.g. _id and slug)
        $query = '*[_id in ' . $refIdsString . ']{ _id, slug, _type }';
        // Build the URL with query and parameters.
        $queryUrl = $this->apiUrl . '/data/query/production?query=' . urlencode($query);

        // Fetch the result.
        $response = $this->apiFetcher->fetchFromApi($queryUrl);
        $result = $response['result'] ?? [];
        $mapping = [];
        foreach ($result as $doc) {
            $mapping[$doc['_id']] = $doc;
        }
        return $mapping;
    }

    /**
     * Recursively substitutes reference arrays in $data with their fetched documents.
     *
     * @param mixed $data
     * @param array $mapping
     * @return mixed
     */
    private function substituteReferences($data, array $mapping)
    {
        if (is_array($data)) {
            if (isset($data['_type']) && $data['_type'] === 'reference' && isset($data['_ref'])) {
                return $mapping[$data['_ref']] ?? $data;
            }
            foreach ($data as $key => $value) {
                $data[$key] = $this->substituteReferences($value, $mapping);
            }
        }
        return $data;
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