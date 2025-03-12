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
        $this->scraperService->processPendingJobs();
        $response->getBody()->write('Job processing completed successfully.');
        return $response->withStatus(200);
    }

    public function savePendingJobs(Request $request, Response $response): Response
    {
        try {
            $documents = $this->cmsClient->getAllDocuments();
            $jobs = $this->cmsClient->fetchAllJobs();

            $newOrUpdatedDocuments = $this->cmsClient->compareDocumentsWithJobs($documents, $jobs);

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
}