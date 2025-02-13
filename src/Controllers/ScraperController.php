<?php

namespace App\Controllers;

use App\Services\ScraperService;
use App\CmsClients\CmsClientInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ScraperController
{
    private ScraperService $scraperService;
    private CmsClientInterface $cmsClient;

    public function __construct(CmsClientInterface $cmsClient, ScraperService $scraperService)
    {
        $this->cmsClient = $cmsClient;
        $this->scraperService = $scraperService;
    }

    public function processPendingJobs(Request $request, Response $response): Response
    {
        $this->scraperService->processPendingJobs();
        $response->getBody()->write('Job processing completed successfully.');
        return $response->withStatus(200);
    }

    public function savePendingJobs(Request $request, Response $response): Response
    {
        $documents = $this->cmsClient->getDocumentsUrls();
        $this->scraperService->savePendingJobs($documents);
        $response->getBody()->write('Jobs saving completed successfully.');
        return $response->withStatus(200);
    }

    public function handleDocumentUpdatedWebhook(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $headers = $request->getHeaders();

        $documentId = $data['sanity_document_id'] ?? null;
        $operation = $data['sanity_operation'] ?? null;

        error_log('Webhook request data: ' . print_r($data, true));
        error_log('Webhook request headers: ' . print_r($headers, true));

        dd('logged');

        if ($documentId && $operation) {
            if (in_array($operation, ['create', 'update'])) {
                $this->scraperService->scrapeDocumentById($documentId);
                $response->getBody()->write('Document updated and scraped successfully.');
            } elseif ($operation === 'delete') {
                $this->scraperService->deleteDocumentById($documentId);
                $response->getBody()->write('Document deleted successfully.');
            } else {
                $response->getBody()->write('Invalid operation.');
                return $response->withStatus(400);
            }
            return $response->withStatus(200);
        }

        $response->getBody()->write('Invalid request: document ID or operation is missing.');
        return $response->withStatus(400);
    }

}