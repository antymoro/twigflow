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

    public function processPendingJobs($jobs): void
    {
        foreach ($jobs as $job) {
            $scrapedDoc = $this->scrapeDocument($job);
            $this->cmsClient->removeJob($job['id']);
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

        foreach ($document['urls'] as $language => $url) {
            $url = $baseUrl . '/' . ltrim($document['urls'][$language], '/');
            $document['content'][$language] = $this->scrapeUrl($url);
        }

        $this->cmsClient->saveDocument($document);

    }

    private function scrapeContent(string $html): string
    {
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        // $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();
        
        $xpath = new \DOMXPath($dom);
    
        // get the first <article> element
        $articleNodes = $xpath->query('//article');
        if (!$articleNodes || $articleNodes->length === 0) {
            return '';
        }
        $article = $articleNodes->item(0);
        
        // find and remove all nodes within the article whose class attribute contains "no-search"
        $nodesToRemove = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " no-search ")]', $article);
        foreach ($nodesToRemove as $node) {
            $node->parentNode->removeChild($node);
        }
        
        // extract the inner HTML of the article element
        $innerHTML = '';
        foreach ($article->childNodes as $child) {
            $innerHTML .= $dom->saveHTML($child);
        }
        
        // optional: Clean up whitespace or perform further processing if needed
        $html = trim($innerHTML);
        $html = str_replace('<br>', ' ', $html);
        $html = str_replace('<p>', ' ', $html);
        $html = str_replace('</p>', ' ', $html);
        $html = str_replace('<div', ' ~~~ <div', $html);
    
        // remove sr-only spans
        while (strpos($html, '<span class="sr-only">')) {
            $i = strpos($html, '<span class="sr-only">');
            $j = strpos($html, '</span>', $i);
            if ($j > $i) {
                $html = substr($html, 0, $i) . substr($html, $j + 7);
            } else {
                break;
            }
        }
    
        // remove all tags
        $html = strip_tags($html);
    
        // remove double spaces and new lines
        for ($i = 1; $i < 10; $i++) {
            $html = str_replace(chr(10) . chr(10), chr(10), $html);
        }
        for ($i = 1; $i < 10; $i++) {
            $html = str_replace('     ', ' ', $html);
        }
        for ($i = 1; $i < 10; $i++) {
            $html = str_replace('  ', ' ', $html);
        }
        for ($i = 1; $i < 10; $i++) {
            $html = str_replace(chr(10) . ' ', chr(10), $html);
        }
        for ($i = 1; $i < 10; $i++) {
            $html = str_replace(chr(10) . chr(10), chr(10), $html);
        }
    
        $html = explode(chr(10), $html);
        foreach ($html as $k => $v) {
            if (is_numeric($v) || strlen($v) < 3) unset($html[$k]);
        }
        $html = implode(chr(10), $html);

        return $html;
    }

    private function scrapeUrl(string $url): mixed
    {
        try {
            $url .= '?829sabdaskasjb';
            $response = $this->client->get($url, ['http_errors' => false]);

            if ($response->getStatusCode() !== 404) {
                $html = (string) $response->getBody();
                // dd($html);
                $content = $this->scrapeContent($html);
                $document = $content;
                // $this->contentRepository->saveContent($document);
            } else {
                error_log("Scraping URL - 404 Not Found: {$url}");
            }
        } catch (RequestException $e) {
            error_log("Error scraping URL {$url}: " . $e->getMessage());
        }

        return $document;
    }

}