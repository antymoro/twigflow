<?php

namespace App\CmsClients\Payload;

use App\Utils\ApiFetcher;


use App\CmsClients\CmsClientInterface;

class PayloadCmsClient implements CmsClientInterface
{
    private string $apiUrl;
    private ApiFetcher $apiFetcher;

    public function __construct(string $apiUrl)
    {
        $this->apiUrl = $apiUrl;
        $this->apiFetcher = new ApiFetcher($this->apiUrl);
    }

    public function getPages(): array
    {
        $url = $this->apiUrl . '/pages';
        $response = $this->apiFetcher->fetchFromApi($url);
        return $response['docs'] ?? [];
    }

    public function getPage(string $slug, ?string $language = null): ?array
    {
        $page = $this->getPageBySlug($slug, $language);

        if (!$page) {
            return null;
        }

        return $this->formatPage($page, $language);

    }

    public function getScaffold(string $global): ?array
    {
        $url = $this->apiUrl . '/globals/' . $global;
        return $this->apiFetcher->fetchFromApi($url);
    }

    private function getPageBySlug(string $slug, ?string $language = null): ?array
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

    private function formatPage($page, ?string $language = null): ?array
    {

        $formattedPage = [];

        $pageModules = $page['content'] ?? [];

        $modulesArray = array_map(function ($module) {

            if (empty($module['is_published'])) {
                return null;
            }

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

    private function getPageById(string $id, ?string $language = null): ?array
    {
        $url = $this->apiUrl . '/pages/' . urlencode($id);
        if ($language) {
            $locale = $this->mapLanguageToLocale($language);
            $url .= '?locale=' . urlencode($locale);
        }
        return $this->apiFetcher->fetchFromApi($url);
    }

    public function getCollectionItem(string $collection, string $slug, ?string $language = null): ?array
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

    public function processData(array $modules, array $globalsConfig, array $asyncData, ?string $language = null): array
    {
        $globals = array_keys($globalsConfig);

        foreach ($asyncData['globals'] as $key => $value) {
            if (in_array($key, $globals) && !empty($value['result'])) {
                $asyncData['globals'][$key] = $value['result'];
            }
        }

        $combinedData = [
            'modules' => $modules,
            'modulesAsyncData' => $asyncData['modulesAsyncData'] ?? [],
            'globals' => $asyncData['globals'] ?? [],
            'metadata' => $asyncData['metadata'] ?? []
        ];

        $processedCombined = $combinedData;

        foreach ($processedCombined['modulesAsyncData'] as $index => $module) {
            foreach ($module as $key => $value) {
                if (!empty($value['result'])) {
                    $processedCombined['modulesAsyncData'][$index][$key] = $value['result'];
                }
            }
        }

        return [
            'modules' => $processedCombined['modules'] ?? [],
            'modulesAsyncData' => $processedCombined['modulesAsyncData'] ?? [],
            'globals' => $processedCombined['globals'] ?? [],
            'metadata' => $processedCombined['metadata'] ?? []
        ];
    }

    public function getDocumentsUrls(): array
    {
        return [];
    }
}
