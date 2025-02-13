<?php

namespace App\CmsClients\Sanity;

use App\CmsClients\Sanity\Components\SanityApiFetcher;
use App\CmsClients\Sanity\Components\SanityDataProcessor;
use App\CmsClients\Sanity\Components\SanityReferenceHandler;
use App\CmsClients\Sanity\Components\SanityUrlBuilder;

use App\CmsClients\CmsClientInterface;

class SanityCmsClient implements CmsClientInterface
{
    private SanityApiFetcher $apiFetcher;
    private SanityDataProcessor $dataProcessor;
    private SanityReferenceHandler $referenceHandler;
    private SanityUrlBuilder $urlBuilder;
    private array $collections = [];

    public function __construct(string $apiUrl)
    {
        $this->apiFetcher = new SanityApiFetcher($apiUrl);
        $this->dataProcessor = new SanityDataProcessor();
        $this->referenceHandler = new SanityReferenceHandler($this->apiFetcher, json_decode(file_get_contents(BASE_PATH . '/application/routes.json'), true));
        $this->urlBuilder = new SanityUrlBuilder();
    }

    public function getPages(): array
    {
        $response = $this->apiFetcher->fetchQuery('*[_type == "page"]');
        return $response['result'] ?? [];
    }

    public function getDocumentsUrls(): array
    {
        if (empty($this->collections)) {
            $this->initializeCollections();
        }

        $this->collections['page'] = ['path' => ''];

        $supportedLanguages = array_filter(explode(',', $_ENV['SUPPORTED_LANGUAGES'] ?? ''));

        $response = $this->apiFetcher->fetchQuery('*[]{_type, slug, _id}', ['disable_cache' => true]);
        $response = $response['result'] ?? [];

        $allDocuments = [];

        foreach ($response as $document) {
            $documents = $this->prepareDocument($document, $supportedLanguages);
            $allDocuments = array_merge($allDocuments, $documents);
        }

        return $allDocuments;
    }

    public function getPage(string $slug, ?string $language = null): ?array
    {
        $query = '*[_type == "page" && slug.current == "' . $slug . '"][0]';
        $response = $this->apiFetcher->fetchQuery($query);
        return $this->formatPage($response, $language);
    }

    public function getCollectionItem(string $collection, string $slug, ?string $language = null): ?array
    {
        $query = '*[_type == "' . $collection . '" && slug.current == "' . $slug . '"][0]';
        $response = $this->apiFetcher->fetchQuery($query);
        return $this->formatPage($response, $language);
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

        $processedCombined = $this->dataProcessor->processDataRecursively($combinedData, $language);

        $references = $this->referenceHandler->fetchReferences($this->dataProcessor->getReferenceIds(), $language);

        $processedReferences = $this->dataProcessor->processDataRecursively($references, $language);

        $processedCombined = $this->referenceHandler->substituteReferences($processedCombined, $processedReferences, $language);

        $processedCombined = $this->referenceHandler->resolveReferenceUrls($processedCombined, $language);


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

    private function formatPage($page, ?string $language = null): ?array
    {
        if (empty($page['result'])) {
            return null;
        }
        $pageModules = $page['result']['modules'] ?? [];

        $modulesArray = array_map(function ($module) {
            $module['type'] = slugify($module['_type'] ?? '');
            return $module;
        }, $pageModules);

        $page['modules'] = $modulesArray;

        return $page;
    }

    public function prepareDocument($document, $supportedLanguages)
    {
        $documents = [];

        if (isset($document['_type']) && isset($this->collections[$document['_type']])
        && isset($document['slug']['current']) && !str_contains($document['_id'],'drafts')) {
            $slug = $document['slug']['current'];
            foreach ($supportedLanguages as $language) {
                $urlPrefix = $language ? '/' . $language : '';
                $url = $urlPrefix . $this->collections[$document['_type']]['path'] . '/' . $slug;

                $documents[] = [
                    'url' => $url,
                    'type' => $document['_type'],
                    'language' => $language,
                    'slug' => $slug,
                    'cms_id' => $document['_id']
                ];
            }
        }

        return $documents;
    }

    private function initializeCollections(): void
    {
        $routesConfig = json_decode(file_get_contents(BASE_PATH . '/application/routes.json'), true);
        $collections = [];

        foreach ($routesConfig as $route => $config) {
            if (isset($config['collection'])) {
                $collectionType = $config['collection'];
                $cleanPath = str_replace('/{slug}', '', $route);
                $collections[$collectionType] = ['path' => $cleanPath];
            }
        }

        $collections['page'] = ['path' => ''];
        $this->collections = $collections;
    }

}