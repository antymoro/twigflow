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

        dd($documents);

        $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $baseUrl = $scheme . '://' . $_SERVER['SERVER_NAME'];
    
        foreach ($documents as $document) {
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

        dd('success');
    }

    private function scrapeContent(string $url): string
    {
        $response = $this->client->get($url);
        $html = (string) $response->getBody();

        // Extract content
        $content = strip_tags($html);

        return $content;    

        return [
            'title' => $this->extractTitle($html),
            'slug' => $this->extractSlug($url),
            'type' => $this->extractType($url),
            'language' => $this->extractLanguage($url, explode(',', $_ENV['SUPPORTED_LANGUAGES'] ?? '')),
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
        $path = parse_url($url, PHP_URL_PATH);
        $segments = explode('/', trim($path, '/'));

        $segment = $segments[1];


        foreach ($this->collections as $type => $config) {
            if ($segment == 'type') {
                return $type;
            }
        }

        return 'page';
    }

    private function extractLanguage(string $url, array $supportedLanguages): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $segments = explode('/', trim($path, '/'));

        if (in_array($segments[0], $supportedLanguages)) {
            return $segments[0];
        }

        return 'default'; // or return a default language if not found
    }


    private function initializeCollections(): void
    {
        $routesConfig = json_decode(file_get_contents(BASE_PATH . '/application/routes.json'), true);
        $collections = [];

        foreach ($routesConfig as $route => $config) {
            if (isset($config['collection'])) {
                $collectionType = $config['collection'];
                $cleanPath = str_replace('/{slug}', '', $route);
                $collections[$collectionType] = ['path' => $cleanPath];
            }
        }

        $collections['page'] = ['path' => ''];
        $this->collections = $collections;
    }
}