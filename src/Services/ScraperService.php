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

    public function __construct(ContentRepository $contentRepository, CmsClientInterface $cmsClient)
    {
        $this->client = new Client();
        $this->contentRepository = $contentRepository;
        $this->cmsClient = $cmsClient;
    }

    public function scrapeAllDocuments(array $apiUrls): void
    {
        foreach ($apiUrls as $apiUrl) {
            $documents = $this->fetchDocumentsFromApi($apiUrl);
            foreach ($documents as $document) {
                $url = $this->resolveUrl($document);
                $content = $this->scrapeContent($url);
                $this->contentRepository->saveContent($content);
            }
        }
    }

    private function fetchDocumentsFromApi(string $apiUrl): array
    {
        $response = $this->client->get($apiUrl);
        return json_decode($response->getBody(), true);
    }

    private function resolveUrl(array $document): string
    {
        // Implement logic to resolve the real URL from the document
        return 'https://example.com/' . $document['slug'];
    }

    private function scrapeContent(string $url): array
    {
        $response = $this->client->get($url);
        $html = (string) $response->getBody();

        // Extract content
        $content = strip_tags($html);
        $title = $this->extractTitle($html);
        $slug = $this->extractSlug($url);
        $type = $this->extractType($url);

        return [
            'title' => $title,
            'slug' => $slug,
            'type' => $type,
            'content' => $content,
        ];
    }

    private function extractTitle(string $html): string
    {
        preg_match('/<title>(.*?)<\/title>/', $html, $matches);
        return $matches[1] ?? 'No Title';
    }

    private function extractSlug(string $url): string
    {
        return basename(parse_url($url, PHP_URL_PATH));
    }

    private function extractType(string $url): string
    {
        // Implement logic to determine type based on URL or other criteria
        return 'page';
    }
}