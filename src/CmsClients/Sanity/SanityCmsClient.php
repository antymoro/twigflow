<?php

namespace App\CmsClients\Sanity;

use App\Utils\ApiFetcher;
use App\CmsClients\Sanity\Components\SanityDataProcessor;
use App\CmsClients\Sanity\Components\SanityReferenceHandler;
use App\CmsClients\Sanity\Components\DocumentsHandler;
use App\CmsClients\CmsClientInterface;
use App\Context\RequestContext;

class SanityCmsClient implements CmsClientInterface
{
    private ApiFetcher $apiFetcher;
    private SanityDataProcessor $dataProcessor;
    private SanityReferenceHandler $referenceHandler;
    private DocumentsHandler $documentsHandler;
    private string $language;

    public function __construct(string $apiUrl, RequestContext $context)
    {
        $this->apiFetcher = new ApiFetcher($apiUrl, $this);
        $this->dataProcessor = new SanityDataProcessor($context);
        $this->referenceHandler = new SanityReferenceHandler($this->apiFetcher, json_decode(file_get_contents(BASE_PATH . '/application/routes.json'), true), $context);
        $this->documentsHandler = new DocumentsHandler();
        $this->language = $context->getLanguage();
    }

    public function getPages(): array
    {
        $response = $this->apiFetcher->fetchFromApi('*[_type == "page"]');
        return $response['result'] ?? [];
    }

    public function getDocumentsUrls(): array
    {
        $supportedLanguages = array_filter(explode(',', $_ENV['SUPPORTED_LANGUAGES'] ?? ''));

        $response = $this->apiFetcher->fetchFromApi('*[]{_type, slug, _id, title}', ['disable_cache' => true]);
        $response = $response['result'] ?? [];

        $allDocuments = [];

        foreach ($response as $document) {
            $documents = $this->documentsHandler->prepareDocument($document, $supportedLanguages);
            $allDocuments = array_merge($allDocuments, $documents);
        }

        return $allDocuments;
    }

    public function getPage(string $slug): ?array
    {
        $query = '*[_type == "page" && slug.current == "' . $slug . '"][0]';
        $response = $this->apiFetcher->fetchFromApi($query);
        return $this->formatPage($response);
    }

    public function getCollectionItem(string $collection, string $slug): ?array
    {
        $query = '*[_type == "' . $collection . '" && slug.current == "' . $slug . '"][0]';
        $response = $this->apiFetcher->fetchFromApi($query);
        return $this->formatPage($response);
    }

    public function processData(array $modules, array $globalsConfig, array $asyncData): array
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

        $processedCombined = $this->dataProcessor->processDataRecursively($combinedData);

        $references = $this->referenceHandler->fetchReferences($this->dataProcessor->getReferenceIds());

        $processedReferences = $this->dataProcessor->processDataRecursively($references);

        $processedCombined = $this->referenceHandler->substituteReferences($processedCombined, $processedReferences);

        $processedCombined = $this->referenceHandler->resolveReferenceUrls($processedCombined);


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

    private function formatPage($page): ?array
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

    public function urlBuilder(string $baseUrl, string $query, array $options): string
    {
        // Sanity-specific URL construction logic
        $encodedQuery = urlencode($query);
        return rtrim($baseUrl, '/') . ltrim($encodedQuery, '/');
    }
}