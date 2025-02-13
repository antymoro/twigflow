<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Repositories\ContentRepository;

class SearchController
{
    private ContentRepository $contentRepository;

    public function __construct(ContentRepository $contentRepository)
    {
        $this->contentRepository = $contentRepository;
    }

    public function search(Request $request, Response $response, array $args): Response
    {
        $queryParams = $request->getQueryParams();
        $query = $queryParams['q'] ?? '';

        $language = $request->getAttribute('language') ?? null;

        $results = $this->contentRepository->searchContent($query, $language);

        // Clean up content and highlight query term in the results
        foreach ($results as &$result) {
            $result['content'] = $this->highlightSections($this->cleanContent($result['content']), $query);
        }

        $response->getBody()->write(json_encode($results));
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function cleanContent(string $content): string
    {
        // Remove unwanted characters and tags
        $content = strip_tags($content);
        $content = preg_replace('/\s+/', ' ', $content);
        return $content;
    }

    private function highlightSections(string $content, string $query): array
    {
        $sections = explode('~~~', $content);
        $highlightedSections = [];

        foreach ($sections as $section) {
            if (stripos($section, $query) !== false) {
                $highlightedSections[] = $this->highlightQuery($section, $query);
            }
        }

        return $highlightedSections;
    }

    private function highlightQuery(string $content, string $query): string
    {
        return preg_replace('/(' . preg_quote($query, '/') . ')/i', '<span>$1</span>', $content);
    }
}