<?php

namespace App\Controllers;

use App\CmsClients\CmsClientInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Repositories\ContentRepository;
use App\Routing\CollectionRoutes;

class SearchController
{
    private ContentRepository $contentRepository;
    private CmsClientInterface $cmsClient;
    private array $collections = [];

    public function __construct(ContentRepository $contentRepository, CmsClientInterface $cmsClient)
    {
        $this->contentRepository = $contentRepository;
        $this->cmsClient = $cmsClient;
    }

    public function search(Request $request, Response $response, array $args): Response
    {
        $queryParams = $request->getQueryParams();
        $query = $queryParams['q'] ?? '';
        $query = sanitize($query);

        $language = $request->getAttribute('language') ?? null;

        // $results = $this->contentRepository->searchContent($query, $language);

        $results = $this->cmsClient->searchContent($query, $language);

        $searchResults = [];

        $this->initializeCollections();

        // Clean up content and highlight query term in the results
        foreach ($results as &$result) {

            // Resolve Title
            $title = $result['title'] ?? '';
            if (is_array($title)) {
                $title = isset($language) && isset($title[$language]) ? $title[$language] : (reset($title) ?: '');
            }
            $result['title'] = $title;

            $fieldsToUnset = ['updated_at', '_rev', '_type', '_id', '_createdAt', '_updatedAt', 'document_id'];
            foreach ($fieldsToUnset as $field) {
                unset($result[$field]);
            }

            // Resolve Content
            $content = $result['content'] ?? '';
            if (is_array($content)) {
                $content = isset($language) && isset($content[$language]) ? $content[$language] : (reset($content) ?: '');
            }
            // Ensure content is string
            if (!is_string($content)) {
                $content = '';
            }

            $result['content'] = $this->highlightSections($this->cleanContent($content), $query);

            $collectionPath = $this->collections[$result['type']]['path'] ?? '';
            $urlLanguagePrefix = $language ? '/' . $language : '';
            $result['url'] =  $urlLanguagePrefix . $collectionPath . '/' . $result['slug'];

            $searchResults[] = $result;
        }

        usort($searchResults, function ($a, $b) use ($query) {
            $aTitleMatch = stripos($a['title'], $query) !== false;
            $bTitleMatch = stripos($b['title'], $query) !== false;

            if ($aTitleMatch && !$bTitleMatch) {
                return -1;
            }
            if (!$aTitleMatch && $bTitleMatch) {
                return 1;
            }

            $aCount = is_array($a['content']) ? count($a['content']) : 0;
            $bCount = is_array($b['content']) ? count($b['content']) : 0;

            return $bCount <=> $aCount;
        });

        $response->getBody()->write(json_encode($searchResults));
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function cleanContent(string $content): string
    {
        // Remove unwanted characters and tags
        $content = strip_tags($content);
        $content = preg_replace('/\s+/', ' ', $content);
        return $content;
    }

    private function highlightSections(string $content, string $query, int $radius = 100): array
    {
        $sections = explode('~~~', $content);
        $highlightedSections = [];

        foreach ($sections as $section) {
            if (stripos($section, $query) !== false) {
                $highlightedSections[] = $this->extractSnippet($section, $query, $radius);
            }
        }

        return $highlightedSections;
    }

    private function extractSnippet(string $text, string $query, int $radius = 100): string
    {
        $textLen = mb_strlen($text);
        $queryLen = mb_strlen($query);
        $lowerText = mb_strtolower($text);
        $lowerQuery = mb_strtolower($query);

        $matches = [];
        $offset = 0;

        while (($pos = mb_strpos($lowerText, $lowerQuery, $offset)) !== false) {
            $matches[] = $pos;
            $offset = $pos + $queryLen;
        }

        if (empty($matches)) {
            return '';
        }

        $ranges = [];
        foreach ($matches as $matchPos) {
            $start = max(0, $matchPos - $radius);
            $end = min($textLen, $matchPos + $queryLen + $radius);

            if (!empty($ranges)) {
                $lastRange = &$ranges[count($ranges) - 1];
                if ($start <= $lastRange['end']) {
                    $lastRange['end'] = max($lastRange['end'], $end);
                    continue;
                }
            }
            $ranges[] = ['start' => $start, 'end' => $end];
        }

        $result = '';
        foreach ($ranges as $i => $range) {
            if ($i > 0) {
                $result .= ' ... ';
            } elseif ($range['start'] > 0) {
                $result .= '... ';
            }

            $length = $range['end'] - $range['start'];
            $chunk = mb_substr($text, $range['start'], $length);

            $chunk = preg_replace('/(' . preg_quote($query, '/') . ')/i', '<span>$1</span>', $chunk);
            $result .= $chunk;

            if ($i === count($ranges) - 1 && $range['end'] < $textLen) {
                $result .= ' ...';
            }
        }

        return $result;
    }

    public function liveSearch(Request $request, Response $response, array $args): Response
    {
        $queryParams = $request->getQueryParams();
        $query = $queryParams['q'] ?? '';
        $query = sanitize($query);

        if (empty($query)) {
            $response->getBody()->write(json_encode([]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $language = $request->getAttribute('language') ?? null;

        $results = $this->cmsClient->searchContent($query, $language);

        $this->initializeCollections();

        $titleMatches = [];
        $contentMatches = [];

        foreach ($results as $result) {
            $title = $result['title'] ?? '';
            if (is_array($title)) {
                $title = isset($language) && isset($title[$language]) ? $title[$language] : (reset($title) ?: '');
            }

            $collectionPath = $this->collections[$result['type']]['path'] ?? '';
            $urlLanguagePrefix = $language ? '/' . $language : '';
            $url = $urlLanguagePrefix . $collectionPath . '/' . $result['slug'];

            $item = [
                'title' => $title,
                'url' => $url,
                'type' => $result['type'],
            ];

            $content = $result['content'] ?? '';
            if (is_array($content)) {
                $content = isset($language) && isset($content[$language]) ? $content[$language] : (reset($content) ?: '');
            }
            if (!is_string($content)) {
                $content = '';
            }
            // Use smaller radius for live search snippets (e.g., 20)
            $snippets = $this->highlightSections($this->cleanContent($content), $query, 20);
            if (!empty($snippets)) {
                $item['content'] = $snippets;
            }

            if (stripos($title, $query) !== false) {
                $titleMatches[] = $item;
            } else {
                $contentMatches[] = $item;
            }
        }

        // Sort title matches: starts-with first, then contains
        usort($titleMatches, function ($a, $b) use ($query) {
            $aStarts = stripos($a['title'], $query) === 0;
            $bStarts = stripos($b['title'], $query) === 0;
            if ($aStarts && !$bStarts) return -1;
            if (!$aStarts && $bStarts) return 1;
            return 0;
        });

        $searchResults = array_merge(
            array_slice($titleMatches, 0, 5),
            array_slice($contentMatches, 0, 3)
        );

        $response->getBody()->write(json_encode($searchResults));
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function initializeCollections(): void
    {
        $this->collections = CollectionRoutes::getCollections();
    }
}
