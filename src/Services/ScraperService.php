<?php

namespace App\Services;

use GuzzleHttp\Client;
use App\Repositories\ContentRepository;
use App\CmsClients\CmsClientInterface;
use GuzzleHttp\Exception\RequestException;

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
                'title' => $document['title'],
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

        try {
            $response = $this->client->get($url, ['http_errors' => false]);

            if ($response->getStatusCode() !== 404) {
                $content = $this->scrapeContent($url);
                $document['content'] = $content;
                $this->contentRepository->saveContent($document);
            } else {
                error_log("Scraping URL - 404 Not Found: {$url}");
            }
        } catch (RequestException $e) {
            error_log("Error scraping URL {$url}: " . $e->getMessage());
        }
    }

    private function scrapeContent(string $url): string
    {
        $response = $this->client->get($url);
        $html = (string) $response->getBody();

        $html=str_replace('<br>',' ',$html);
        $html=str_replace('<p>',' ',$html);
        $html=str_replace('</p>',' ',$html);
        $html=str_replace('<div',' ~~~ <div',$html);

        // remove sr-only spans
        while (strpos($html,'<span class="sr-only">'))
        {
            $i=strpos($html,'<span class="sr-only">');
            $j=strpos($html,'</span>',$i);
            if ($j>$i) $html=substr($html,0,$i).substr($html,$j+7);
                else break;
        }

        // remove all tags
        $html=strip_tags($html);

        // remove double spaces and new lines
        for ($i=1;$i<10;$i++)
            $html=str_replace(chr(10).chr(10),chr(10),$html);
        for ($i=1;$i<10;$i++)
            $html=str_replace('     ',' ',$html);
        for ($i=1;$i<10;$i++)
            $html=str_replace('  ',' ',$html);
        for ($i=1;$i<10;$i++)
            $html=str_replace(chr(10).' ',chr(10),$html);
        for ($i=1;$i<10;$i++)
            $html=str_replace(chr(10).chr(10),chr(10),$html);

        $html=explode(chr(10),$html);
        foreach ($html as $k=>$v)
            if (is_numeric($v) || strlen($v)<3) unset($html[$k]);
        $html=implode(chr(10),$html);

        return $html;
    }
}