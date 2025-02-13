<?php

namespace App\Services;

use GuzzleHttp\Client;
use App\Repositories\ContentRepository;
use App\CmsClients\CmsClientInterface;

class ScraperService
{
    private Client $client;
    private ContentRepository $contentRepository;
    private CmsClientInterface $cmsClient;
    private array $collections = [];

    public function __construct(ContentRepository $contentRepository, CmsClientInterface $cmsClient)
    {
        $this->client = new Client();
        $this->contentRepository = $contentRepository;
        $this->cmsClient = $cmsClient;
    }

    public function scrapeAllDocuments(array $documents): void
    {
        foreach ($documents as $document) {
            $this->contentRepository->saveJob([
                'url' => $document['url'],
                'type' => $document['type'],
                'language' => $document['language'],
                'slug' => $document['slug'],
                'cms_id' => $document['cms_id'],
                'status' => 'pending',
            ]);
        }
    }

    public function processPendingJobs(): void
    {
        $jobs = $this->contentRepository->getPendingJobs();

        foreach ($jobs as $job) {
            $this->scrapeDocument($job);
            $this->contentRepository->updateJobStatus($job['id'], 'completed');
        }
    }

    public function savePendingJobs(array $documents): void
    {
        foreach ($documents as $document) {
            $this->contentRepository->saveJob([
                'url' => $document['url'],
                'type' => $document['type'],
                'language' => $document['language'],
                'slug' => $document['slug'],
                'cms_id' => $document['cms_id'],
                'status' => 'pending',
            ]);
        }
    }

    public function scrapeDocument(array $document): void
    {
        $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $baseUrl = $scheme . '://' . $_SERVER['SERVER_NAME'];
        $url = $baseUrl . '/' . ltrim($document['url'], '/');
        $response = $this->client->get($url, ['http_errors' => false]);

        if ($response->getStatusCode() !== 404) {
            $content = $this->scrapeContent($url);
            $document['content'] = $content;
            $this->contentRepository->saveContent($document);
        } else {
            error_log("404 Not Found: {$url}");
        }
    }

    private function scrapeContent(string $url): string
    {
        $response = $this->client->get($url);
        $html = (string) $response->getBody();

        // Extract content
        $content = strip_tags($html);

        return $content;
    }
}