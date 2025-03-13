<?php

namespace App\CmsClients\Payload;

use App\Parsers\LexicalRichTextParser;
use App\Processors\RecursiveProcessors\LexicalRichTextFieldProcessor;
use App\Processors\UniversalRecursiveProcessor;
use App\Utils\ApiFetcher;
use App\CmsClients\CmsClientInterface;
use App\Context\RequestContext;

class PayloadCmsClient implements CmsClientInterface
{
    private string $apiUrl;
    private ApiFetcher $apiFetcher;
    private RequestContext $context;

    public function __construct(string $apiUrl, RequestContext $context)
    {
        $this->apiUrl = $apiUrl;
        $this->apiFetcher = new ApiFetcher($this->apiUrl, $this);
        $this->context = $context;
    }

    public function getPages(): array
    {
        return [];
    }

    public function getPage(string $slug): ?array
    {
        $page = $this->getPageBySlug($slug);

        if (!$page) {
            return null;
        }

        return $this->formatPage($page);
    }

    public function getScaffold(string $global): ?array
    {
        return [];
    }

    private function getPageBySlug(string $slug): ?array
    {
        $query = '/pages?where[slug][equals]=' . $slug;
        $response = $this->apiFetcher->fetchFromApi($query);

        foreach ($response['docs'] as $doc) {
            if ($doc['slug'] === $slug) {
                return $doc;
            }
        }
        return null;
    }

    private function formatPage($page): ?array
    {
        $formattedPage = [];

        $pageModules = $page['content'] ?? [];

        $modulesArray = array_map(function ($module) {
            // if (empty($module['is_published'])) {
            //     return null;
            // }

            $module['type'] = slugify($module['blockType'] ?? '');
            unset($module['blockType']);
            return $module;
        }, $pageModules);

        $modulesArray = array_filter($modulesArray);

        unset($page['content']);

        $formattedPage['result'] = $page;
        $formattedPage['modules'] = $modulesArray;

        return $formattedPage;
    }

    private function getPageById(string $id): ?array
    {
        $url = $this->apiUrl . '/pages/' . urlencode($id);
        $language = $this->context->getLanguage();
        if ($language) {
            $locale = $this->mapLanguageToLocale($language);
            $url .= '?locale=' . urlencode($locale);
        }
        return $this->apiFetcher->fetchFromApi($url);
    }

    public function getCollectionItem(string $collection, string $slug): ?array
    {
        return [];
    }

    private function mapLanguageToLocale(string $language): string
    {
        $locales = [
            'en' => 'en-US',
            'pl' => 'pl-PL',
        ];
        return $locales[$language] ?? 'en-US';
    }

    public function processData(array $modules, array $globalsConfig, array $asyncData): array
    {
        $this->updateGlobals($asyncData, $globalsConfig);
        $combinedData = $this->combineData($modules, $asyncData);

        $lexicalParser = new LexicalRichTextParser();
        $processors = [
            new LexicalRichTextFieldProcessor($lexicalParser),
        ];

        $universalParser = new UniversalRecursiveProcessor($processors);
        $combinedData = $universalParser->parseRecursive($combinedData);

        $processedCombined = $combinedData;
        $this->updateModulesAsyncData($processedCombined);

        return $this->extractProcessedData($processedCombined);
    }

    private function updateGlobals(array &$asyncData, array $globalsConfig): void
    {
        $globals = array_keys($globalsConfig);

        foreach ($asyncData['globals'] as $key => $value) {
            if (in_array($key, $globals) && !empty($value['result'])) {
                $asyncData['globals'][$key] = $value['result'];
            }
        }
    }

    private function combineData(array $modules, array $asyncData): array
    {
        return [
            'modules' => $modules,
            'modulesAsyncData' => $asyncData['modulesAsyncData'] ?? [],
            'globals' => $asyncData['globals'] ?? [],
            'metadata' => $asyncData['metadata'] ?? []
        ];
    }

    private function updateModulesAsyncData(array &$processedCombined): void
    {
        foreach ($processedCombined['modulesAsyncData'] as $index => $module) {
            foreach ($module as $key => $value) {
                if (!empty($value['result'])) {
                    $processedCombined['modulesAsyncData'][$index][$key] = $value['result'];
                }
            }
        }
    }

    private function extractProcessedData(array $processedCombined): array
    {
        return [
            'modules' => $processedCombined['modules'] ?? [],
            'modulesAsyncData' => $processedCombined['modulesAsyncData'] ?? [],
            'globals' => $processedCombined['globals'] ?? [],
            'metadata' => $processedCombined['metadata'] ?? []
        ];
    }

    public function urlBuilder(string $baseUrl, string $query, array $options): string
    {
        return rtrim($baseUrl, '/') . '/' . ltrim($query, '/');
    }

    public function getDocumentsUrls($jobs): array
    {
        return [];
    }
}