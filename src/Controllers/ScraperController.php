<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Services\ScraperService;
use App\CmsClients\CmsClientInterface;

class ScraperController
{
    private CmsClientInterface $cmsClient;
    private ScraperService $scraperService;

    public function __construct(CmsClientInterface $cmsClient, ScraperService $scraperService)
    {
        $this->cmsClient = $cmsClient;
        $this->scraperService = $scraperService;
    }

    public function scrape(Request $request, Response $response, array $args): Response
    {

        $documentsUrls = $this->cmsClient->getDocumentsUrls();
        $this->scraperService->scrapeAllDocuments($documentsUrls);

        $response->getBody()->write("Scraping completed successfully.");
        return $response;
    }
}