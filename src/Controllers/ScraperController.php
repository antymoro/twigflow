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

    /**
     * Scraper Workflow:
     * 1. GET /api/scraper/init     - Compares CMS content with indexed content and queues jobs.
     * 2. GET /api/scraper/process  - Processes a batch of queued jobs (scrape & index). Run repeatedly until empty.
     * 3. GET /api/scraper/prune    - Removes indexed content that no longer exists in CMS.
     *
     * Maintenance:
     * - GET /api/scraper/reset     - Clears the job queue forcefully.
     */
    public function __construct(CmsClientInterface $cmsClient, ScraperService $scraperService, ApiFetcher $apiFetcher)
    {
        $this->cmsClient = $cmsClient;
        $this->scraperService = $scraperService;
        $this->apiFetcher = $apiFetcher;
    }

    /**
     * Step 2: Process the Queue.
     * Fetches pending jobs, scrapes the content, stores it, and removes the job.
     * Accepts optional 'limit' query param (default: 5).
     */
    public function processQueue(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $limit = isset($queryParams['limit']) ? (int)$queryParams['limit'] : 5;

        $jobs = $this->cmsClient->fetchAllJobs($limit);

        // filter jobs that have status pending
        $pendingJobs = array_filter($jobs, function ($job) {
            return $job['status'] === 'pending';
        });

        if (empty($pendingJobs)) {
            $response->getBody()->write(json_encode([
                'status' => 'success',
                'message' => 'No pending jobs found.',
                'processed_count' => 0
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        }

        $pendingJobs = $this->cmsClient->getDocumentsUrls($pendingJobs);

        $this->scraperService->processPendingJobs($pendingJobs);

        $response->getBody()->write(json_encode([
            'status' => 'success',
            'message' => 'Batch processing completed.',
            'processed_count' => count($pendingJobs)
        ]));

        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

    /**
     * Step 1: Initialize the Queue.
     * Identifies content that needs scraping (new or updated) and creates jobs for them.
     */
    public function initQueue(Request $request, Response $response): Response
    {
        try {
            $documents = $this->cmsClient->getAllDocuments();
            $scrapedDocuments = $this->cmsClient->getScrapedDocuments();

            $jobs = $this->cmsClient->fetchAllJobs();

            $newOrUpdatedDocuments = $this->cmsClient->compareDocumentsWithScrapedDocuments($documents, $scrapedDocuments);
            $newOrUpdatedDocuments = $this->cmsClient->compareDocumentsWithPendingJobs($newOrUpdatedDocuments, $jobs);

            if (empty($newOrUpdatedDocuments)) {
                $response->getBody()->write(json_encode([
                    'status' => 'success',
                    'message' => 'No new or updated documents to queue.',
                    'queued_count' => 0
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
            }

            foreach ($newOrUpdatedDocuments as &$document) {
                $title = $document['title'] ?? $document['name'] ?? $document['label'] ?? [];
                $document['title'] = $title;
            }

            $batchSize = 50;
            $batches = array_chunk($newOrUpdatedDocuments, $batchSize);

            $allSuccess = true;
            $apiError = null;
            foreach ($batches as $batch) {
                $requestBody = $this->cmsClient->getPostData($batch);
                $success = $this->apiFetcher->postToApi($requestBody, $apiError);
                if (!$success) {
                    $allSuccess = false;
                    break;
                }
            }

            if ($allSuccess) {
                $response->getBody()->write(json_encode([
                    'status' => 'success',
                    'message' => 'Jobs queued successfully.',
                    'queued_count' => count($newOrUpdatedDocuments)
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
            } else {
                $msg = 'Failed to save jobs.';
                if (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'development' && $apiError) {
                    $msg .= ' Error: ' . $apiError;
                }
                $response->getBody()->write(json_encode([
                    'status' => 'error',
                    'message' => $msg
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }
        } catch (\Exception $e) {
            // $this->logger->error('Error saving jobs: ' . $e->getMessage(), ['exception' => $e]);
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => 'An error occurred while saving jobs: ' . $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Maintenance: Reset Queue.
     * Clears all pending jobs. Requires authorization token.
     */
    public function resetQueue(Request $request, Response $response): Response
    {
        // Check if the specific query string is present
        $queryParams = $request->getQueryParams();
        if (!isset($queryParams['829sabdaskasjb'])) {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Unauthorized request.']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // Proceed with clearing pending jobs
        $this->cmsClient->clearAllJobs();

        $response->getBody()->write(json_encode(['status' => 'success', 'message' => 'Job queue cleared.']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

    /**
     * Step 3: Prune Index.
     * Removes scraped documents that no longer exist in the source CMS.
     */
    public function pruneIndex(Request $request, Response $response): Response
    {
        $documents = $this->cmsClient->getAllDocuments();
        $scrapedDocuments = $this->cmsClient->getScrapedDocuments();

        $deleteFromSearch = $this->cmsClient->getDocumentsToDeleteFromSearch($documents, $scrapedDocuments);

        if (empty($deleteFromSearch)) {
            $response->getBody()->write(json_encode([
                'status' => 'success',
                'message' => 'Index is clean. No documents to prune.',
                'pruned_count' => 0
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        }

        $success = $this->cmsClient->deleteFromSearch($deleteFromSearch);
        if (!$success) {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Failed to delete documents from search.']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        $response->getBody()->write(json_encode([
            'status' => 'success',
            'message' => 'Search results pruned successfully.',
            'pruned_count' => count($deleteFromSearch)
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }
}
