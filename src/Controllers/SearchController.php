<?php

namespace App\Controllers;

use App\CmsClients\CmsClientInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Repositories\ContentRepository;
use App\Utils\Helpers;

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
