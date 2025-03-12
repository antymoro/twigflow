<?php

namespace App\CmsClients\Sanity;

use App\Utils\ApiFetcher;
use App\CmsClients\Sanity\Components\SanityDataProcessor;
use App\CmsClients\Sanity\Components\SanityReferenceHandler;
use App\CmsClients\Sanity\Components\DocumentsHandler;
use App\CmsClients\CmsClientInterface;
use App\Context\RequestContext;

use GuzzleHttp\Client;

class SanityCmsClient implements CmsClientInterface
{
    private ApiFetcher $apiFetcher;
    private SanityDataProcessor $dataProcessor;
    private SanityReferenceHandler $referenceHandler;
    private DocumentsHandler $documentsHandler;
    private array $routes;

    public function __construct(string $apiUrl, RequestContext $context)
    {
        $this->apiFetcher = new ApiFetcher($apiUrl, $this);
        $this->dataProcessor = new SanityDataProcessor($context);
        $this->routes = json_decode(file_get_contents(BASE_PATH . '/application/routes.json'), true);
        $this->referenceHandler = new SanityReferenceHandler($this->apiFetcher, $this->routes, $context);
        $this->documentsHandler = new DocumentsHandler();
    }

    public function getPages(): array
    {
        $response = $this->apiFetcher->fetchFromApi('*[_type == "page"]');
        return $response['result'] ?? [];
    }

    public function getAllDocuments() : array
    {
        $response = $this->apiFetcher->fetchFromApi('*[]{_type, slug, _id, title, _createdAt, _updatedAt}', ['disable_cache' => true]);
        $collections = [];

        foreach ($this->routes as $route) {
            if (isset($route['collection'])) {
                $collections[] = $route['collection'];
            }
        }

        $allDocuments = $response['result'] ?? [];

        $documentsToScrap = [];

        foreach ($allDocuments as $document) {
            if (in_array($document['_type'], $collections) && !str_contains($document['_id'], 'drafts.')) {
                $documentsToScrap[] = $document;
            }
        }

        return $documentsToScrap;

    }


    public function fetchAllJobs(): array
    {
        $query = '*[_type == "jobs"]';
        $response = $this->apiFetcher->fetchFromApi($query);
        return $response['result'] ?? [];
    }

    public function getDocumentsUrls(): array
    {
        $supportedLanguages = array_filter(explode(',', $_ENV['SUPPORTED_LANGUAGES'] ?? ''));

        $response = $this->apiFetcher->fetchFromApi('*[]{_type, slug, _id, title, _createdAt, _updatedAt}', ['disable_cache' => true]);
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
        $this->updateGlobals($asyncData, $globalsConfig);
        $combinedData = $this->combineData($modules, $asyncData);

        $processedCombined = $this->dataProcessor->processDataRecursively($combinedData);

        $processedCombined = $this->processReferences($processedCombined);

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

    private function processReferences(array $processedCombined): array
    {
        $references = $this->referenceHandler->fetchReferences($this->dataProcessor->getReferenceIds());
        $processedReferences = $this->dataProcessor->processDataRecursively($references);

        $processedCombined = $this->referenceHandler->substituteReferences($processedCombined, $processedReferences);
        return $this->referenceHandler->resolveReferenceUrls($processedCombined);
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

    public function getPostData(array $documents): array
    {
        $mutations = array_map(function ($document) {
            return [
                'createOrReplace' => $this->formatDocumentForJobsCollection($document),
            ];
        }, $documents);

        return ['mutations' => $mutations];
    }

    private function formatDocumentForJobsCollection(array $document): array
    {
        return [
            '_type' => 'jobs',
            'document_id' => $document['_id'],
            'created_at' => $document['_createdAt'],
            'type' => $document['_type'],
            'slug' => $document['slug']['current'] ?? '',
            'status' => 'pending',
            'updated_at' => $document['_updatedAt'],
        ];
    }

    public function compareDocumentsWithJobs(array $documents, array $jobs): array
    {
        $jobsById = [];
        foreach ($jobs as $job) {
            $jobsById[$job['document_id']] = $job;
        }
    
        $newOrUpdatedDocuments = [];
        foreach ($documents as $document) {
            $documentId = $document['_id'];
            $documentUpdatedAt = strtotime($document['_updatedAt']);
    
            if (!isset($jobsById[$documentId])) {
                $newOrUpdatedDocuments[] = $document;
            } else {
                $job = $jobsById[$documentId];
                $jobUpdatedAt = strtotime($job['updated_at']);
                $jobStatus = $job['status'];
    
                if ($documentUpdatedAt > $jobUpdatedAt && $jobStatus !== 'pending') {
                    $newOrUpdatedDocuments[] = $document;
                }
            }
        }
    
        return $newOrUpdatedDocuments;
    }
}