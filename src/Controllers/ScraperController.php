<?php

namespace App\Controllers;

use App\Services\ScraperService;
use App\CmsClients\CmsClientInterface;
use App\Utils\ApiFetcher;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ScraperController
{
    private ScraperService $scraperService;
    private CmsClientInterface $cmsClient;
    private ApiFetcher $apiFetcher;

    public function __construct(CmsClientInterface $cmsClient, ScraperService $scraperService, ApiFetcher $apiFetcher)
    {
        $this->cmsClient = $cmsClient;
        $this->scraperService = $scraperService;
        $this->apiFetcher = $apiFetcher;
    }

    public function processPendingJobs(Request $request, Response $response): Response
    {
        $jobs = $this->cmsClient->fetchAllJobs(3);

        // filter jobs that have status pending
        $pendingJobs = array_filter($jobs, function ($job) {
            return $job['status'] === 'pending';
        });

        $pendingJobs = $this->cmsClient->getDocumentsUrls($pendingJobs);

        //TODO add batching here & update the status of the job to processing
        $this->scraperService->processPendingJobs($pendingJobs);


        $response->getBody()->write('Job processing completed successfully.');
        return $response->withStatus(200);
    }

    public function savePendingJobs(Request $request, Response $response): Response
    {
        try {
            $documents = $this->cmsClient->getAllDocuments();
            $scrapedDocuments = $this->cmsClient->getScrapedDocuments();

            $jobs = $this->cmsClient->fetchAllJobs();

            $newOrUpdatedDocuments = $this->cmsClient->compareDocumentsWithScrapedDocuments($documents, $scrapedDocuments);
            $newOrUpdatedDocuments = $this->cmsClient->compareDocumentsWithPendingJobs($newOrUpdatedDocuments, $jobs);

            foreach ($newOrUpdatedDocuments as &$document) {
                $title = $document['title'] ?? $document['name'] ?? $document['label'] ?? [];
                $document['title'] = $title;
            }

            $batchSize = 50;
            $batches = array_chunk($newOrUpdatedDocuments, $batchSize);

            $allSuccess = true;
            foreach ($batches as $batch) {
                $requestBody = $this->cmsClient->getPostData($batch);
                $success = $this->apiFetcher->postToApi($requestBody);
                if (!$success) {
                    $allSuccess = false;
                    break;
                }
            }

            if ($allSuccess) {
                $response->getBody()->write('Jobs saving completed successfully.');
                return $response->withStatus(200);
            } else {
                $response->getBody()->write('Failed to save jobs.');
                return $response->withStatus(500);
            }
        } catch (\Exception $e) {
            $this->logger->error('Error saving jobs: ' . $e->getMessage(), ['exception' => $e]);
            $response->getBody()->write('An error occurred while saving jobs: ' . $e->getMessage());
            return $response->withStatus(500);
        }
    }

    public function clearPendingJobs(Request $request, Response $response): Response
    {
        // Check if the specific query string is present
        $queryParams = $request->getQueryParams();
        if (!isset($queryParams['829sabdaskasjb'])) {
            $response->getBody()->write('Unauthorized request.');
            return $response->withStatus(403); // Forbidden
        }
    
        // Proceed with clearing pending jobs
        $jobs = $this->cmsClient->clearAllJobs();
    
        $response->getBody()->write('Job processing completed successfully.');
        return $response->withStatus(200);
    }

    public function updateSearchResults(Request $request, Response $response): Response
    {
        $documents = $this->cmsClient->getAllDocuments();
        $scrapedDocuments = $this->cmsClient->getScrapedDocuments();

        $deleteFromSearch = $this->cmsClient->getDocumentsToDeleteFromSearch($documents, $scrapedDocuments);

        $success = $this->cmsClient->deleteFromSearch($deleteFromSearch);
        if (!$success) {
            $response->getBody()->write('Failed to delete documents from search.');
            return $response->withStatus(500);
        }

        $response->getBody()->write('Search results updated successfully.');
        return $response->withStatus(200);
    }

}