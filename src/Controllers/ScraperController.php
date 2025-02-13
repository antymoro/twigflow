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

}