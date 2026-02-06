<?php

namespace App\CmsClients\Sanity;

use App\Utils\ApiFetcher;
use App\CmsClients\Sanity\SanityDataProcessor;
use App\CmsClients\Sanity\SanityReferenceHandler;
use App\CmsClients\Sanity\DocumentsHandler;
use App\CmsClients\AbstractCmsClient;
use App\Context\RequestContext;

class SanityCmsClient extends AbstractCmsClient
{
    private ApiFetcher $apiFetcher;
    private SanityDataProcessor $dataProcessor;
    private SanityReferenceHandler $referenceHandler;
    private DocumentsHandler $documentsHandler;
    private array $routes;
    private RequestContext $context;

    public function __construct(string $apiUrl, RequestContext $context, ApiFetcher $apiFetcher)
    {
        $this->apiFetcher = $apiFetcher;
        $this->dataProcessor = new SanityDataProcessor($context);
        $this->routes = json_decode(file_get_contents(BASE_PATH . '/application/routes.json'), true);
        $this->referenceHandler = new SanityReferenceHandler($this->apiFetcher, $this->routes, $context);
        $this->documentsHandler = new DocumentsHandler();
        $this->context = $context;
    }

    public function getPages(): array
    {
        $response = $this->apiFetcher->fetchFromApi('*[_type == "page"]');
        return $response['result'] ?? [];
    }

    public function getAllDocuments(): array
    {
        $response = $this->apiFetcher->fetchFromApi('*[]{_type, slug, _id, title, name, label, _createdAt, _updatedAt}', ['disable_cache' => true]);
        $collections = [];

        foreach ($this->routes as $route) {
            if (isset($route['collection'])) {
                $collections[] = $route['collection'];
            }
        }

        $collections[] = 'page';

        $allDocuments = $response['result'] ?? [];

        $documentsToScrap = [];

        foreach ($allDocuments as $document) {
            if (in_array($document['_type'], $collections) && !str_contains($document['_id'], 'drafts.')) {
                $documentsToScrap[] = $document;
            }
        }

        return $documentsToScrap;
    }

    public function getScrapedDocuments(): array
    {
        $query = '*[_type == "scraped_documents"]';
        $response = $this->apiFetcher->fetchFromApi($query);
        return $response['result'] ?? [];
    }

    public function fetchAllJobs($quantity = null): array
    {
        $query = '*[_type == "jobs"]' . ($quantity ? '[0...' . $quantity . ']' : '');
        $response = $this->apiFetcher->fetchFromApi($query);
        return $response['result'] ?? [];
    }

    public function getDocumentsUrls($jobs): array
    {
        $supportedLanguages = $this->context->getSupportedLanguages();

        $allDocuments = [];

        foreach ($jobs as $document) {
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

    private function processReferences(array $processedCombined): array
    {
        $references = $this->referenceHandler->fetchReferences($this->dataProcessor->getReferenceIds());
        $processedReferences = $this->dataProcessor->processDataRecursively($references);

        $processedCombined = $this->referenceHandler->substituteReferences($processedCombined, $processedReferences);
        return $this->referenceHandler->resolveReferenceUrls($processedCombined);
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
        $formattedDoc = [
            '_type' => 'jobs',
            '_id' => $document['_id'] . '-job',
            'title' => $document['title'],
            'document_id' => $document['_id'],
            'created_at' => $document['_createdAt'],
            'type' => $document['_type'],
            'slug' => $document['slug']['current'] ?? '',
            'status' => 'pending',
            'updated_at' => $document['_updatedAt'],
        ];

        return $formattedDoc;
    }

    public function compareDocumentsWithScrapedDocuments(array $documents, array $scrapedDocuments): array
    {
        $scrapedDocumentsById = [];
        $newOrUpdatedDocuments = [];

        // Index scraped documents by document_id
        foreach ($scrapedDocuments as $scrapedDocument) {
            $scrapedDocumentsById[$scrapedDocument['document_id']] = $scrapedDocument;
        }

        // Compare documents with scraped documents
        foreach ($documents as $document) {
            $documentId = $document['_id'];
            $documentUpdatedAt = strtotime($document['_updatedAt']);

            if (!isset($scrapedDocumentsById[$documentId])) {
                // Document is not in scraped documents, add it to jobs
                $newOrUpdatedDocuments[] = $document;
            } else {
                // Document is in scraped documents, compare updated_at timestamp
                $scrapedDocument = $scrapedDocumentsById[$documentId];
                $scrapedDocumentUpdatedAt = strtotime($scrapedDocument['updated_at']);

                if ($documentUpdatedAt > $scrapedDocumentUpdatedAt) {
                    // Document has a newer updated_at timestamp, add it to jobs
                    $newOrUpdatedDocuments[] = $document;
                }
            }
        }

        return $newOrUpdatedDocuments;
    }

    public function getDocumentsToDeleteFromSearch(array $documents, array $scrapedDocuments): array
    {
        $documentIds = [];
        $documentsToDelete = [];

        // Collect all document IDs from the normal documents
        foreach ($documents as $document) {
            $documentIds[$document['_id']] = true;
        }

        // Compare scraped documents against normal documents
        foreach ($scrapedDocuments as $scrapedDocument) {
            $scrapedDocumentId = $scrapedDocument['document_id'];

            if (!isset($documentIds[$scrapedDocumentId])) {
                // If the scraped document's document_id is not in the normal documents, mark it for deletion
                $documentsToDelete[] = $scrapedDocument['_id'];
            }
        }

        return $documentsToDelete;
    }

    public function compareDocumentsWithPendingJobs(array $documents, array $pendingJobs): array
    {
        $scrapedDocumentsById = [];
        $newOrUpdatedDocuments = [];

        // Index scraped documents by document_id
        foreach ($pendingJobs as $scrapedDocument) {
            $scrapedDocumentsById[$scrapedDocument['document_id']] = $scrapedDocument;
        }

        // Compare documents with scraped documents
        foreach ($documents as $document) {
            $documentId = $document['_id'];

            if (!isset($scrapedDocumentsById[$documentId])) {
                $newOrUpdatedDocuments[] = $document;
            }
        }

        return $newOrUpdatedDocuments;
    }

    public function clearAllJobs(): bool
    {
        // Define the types to clear
        $typesToClear = ['jobs', 'scraped_documents'];
        $allMutations = [];

        foreach ($typesToClear as $type) {
            // Fetch all documents of the current type
            $query = '*[_type == "' . $type . '"]';
            $response = $this->apiFetcher->fetchFromApi($query);
            $documents = $response['result'] ?? [];

            if (!empty($documents)) {
                // Create delete mutations for the documents
                $mutations = array_map(function ($document) {
                    return [
                        'delete' => ['id' => $document['_id']],
                    ];
                }, $documents);

                // Merge mutations into the main array
                $allMutations = array_merge($allMutations, $mutations);
            }
        }

        // If there are no documents to delete, return true
        if (empty($allMutations)) {
            return true;
        }

        // Send the delete mutations to the API
        $requestBody = ['mutations' => $allMutations];
        return $this->apiFetcher->postToApi($requestBody);
    }

    public function saveDocument(array $document): bool
    {
        $uniqueId = $document['document_id'] . '-scraped';

        $mutations = [
            [
                'createOrReplace' => [
                    '_type' => 'scraped_documents',
                    '_id' => $uniqueId,
                    'title' => $document['title'],
                    'content' => $document['content'],
                    'document_id' => $document['document_id'],
                    'created_at' => $document['created_at'],
                    'type' => $document['type'],
                    'slug' => $document['slug'],
                    'updated_at' => $document['updated_at'],
                ],
            ],
        ];

        $requestBody = ['mutations' => $mutations];

        return $this->apiFetcher->postToApi($requestBody);
    }

    public function removeJob(string $jobId): bool
    {
        $mutations = [
            [
                'delete' => ['id' => $jobId],
            ],
        ];

        $requestBody = ['mutations' => $mutations];

        return $this->apiFetcher->postToApi($requestBody);
    }

    public function deleteFromSearch($items)
    {
        $mutations = array_map(function ($item) {
            return [
                'delete' => ['id' => $item],
            ];
        }, $items);

        $requestBody = ['mutations' => $mutations];

        return $this->apiFetcher->postToApi($requestBody);
    }

    public function searchContent(string $query, ?string $language = null): array
    {
        $wildcardQuery = $query . '*';

        if ($language) {
            $sanityQuery = '*[_type == "scraped_documents" && content.' . $language . ' match "' . $wildcardQuery . '"]';
        } else {
            $sanityQuery = '*[_type == "scraped_documents" && content match "' . $wildcardQuery . '"]';
        }

        $response = $this->apiFetcher->fetchFromApi($sanityQuery);
        return $response['result'] ?? [];
    }
}
