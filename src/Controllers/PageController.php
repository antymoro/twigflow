<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use App\CmsClients\CmsClientInterface;
use App\Utils\HtmlUpdater;
use App\Processors\DataProcessor;
use App\Services\CacheService;
use App\Context\RequestContext;

class PageController
{
    private Twig $view;
    private CmsClientInterface $cmsClient;
    private string $templatePath;
    private string $userTemplatePath;
    private DataProcessor $dataProcessor;
    private CacheService $cacheService;
    private RequestContext $context;

    /**
     * Constructor to initialize dependencies.
     *
     * @param Twig $view
     * @param DataProcessor $dataProcessor
     * @param CmsClientInterface $cmsClient
     * @param CacheService $cacheService
     * @param RequestContext $context
     * @param string $templatePath
     */
    public function __construct(Twig $view, DataProcessor $dataProcessor, CmsClientInterface $cmsClient, CacheService $cacheService, RequestContext $context, string $templatePath = 'src/views/')
    {
        $this->view = $view;
        $this->dataProcessor = $dataProcessor;
        $this->cmsClient = $cmsClient;
        $this->cacheService = $cacheService;
        $this->context = $context;
        $this->templatePath = $templatePath;
        $this->userTemplatePath = BASE_PATH . '/application/views/';
    }

    /**
     * Handles incoming page requests.
     *
     * @param Request $request
     * @param Response $response
     * @param array $args Contains route arguments (e.g., slug, language)
     * @return Response
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $slug = $args['slug'] ?? '';
        $this->context->setLanguage($request->getAttribute('language') ?? null);

        return $this->handlePageRequest($request, $response, $slug);
    }

    /**
     * Show a collection item, determined by the "collection" attribute and "slug" argument.
     */
    public function showCollectionItem(Request $request, Response $response, array $args): Response
    {
        $collection = $request->getAttribute('collection');
        $slug = $args['slug'] ?? null;
        $this->context->setLanguage($request->getAttribute('language') ?? null);

        return $this->handlePageRequest($request, $response, $slug, $collection);
    }

    /**
     * Show the homepage, falling back to "homepage" if not set in the environment.
     */
    public function showHomepage(Request $request, Response $response): Response
    {
        $homepageSlug = $_ENV['HOMEPAGE_SLUG'] ?? 'homepage';
        return $this->show($request, $response, ['slug' => $homepageSlug]);
    }

    /**
     * Common logic to handle page requests.
     */
    private function handlePageRequest(Request $request, Response $response, string $slug, ?string $collection = null): Response
    {
        // Build unique cache key.
        $language = $this->context->getLanguage() ?? '';
        $queryParams = $request->getQueryParams();
        $queryString = http_build_query($queryParams);
        $fullUrl = (string) $request->getUri()->withQuery($queryString);
        $uniqueKey = 'page_' . md5($fullUrl . '_' . $language);
        $shared404Key  = 'page_404_' . $this->context->getLanguage();

        // Determine expected page type.
        $routesConfig = $request->getAttribute('routesConfig') ?? [];
        $pageType = 'page';
        foreach ($routesConfig as $config) {
            if (isset($config['collection']) && $config['collection'] === $collection && isset($config['page'])) {
                $pageType = $config['page'];
            }
        }

        // Try retrieving from cache.
        $pageData = $this->cacheService->fetch($uniqueKey);
        if ($pageData !== null) {
            // If cached item is a Response (404), return it directly.
            if ($pageData instanceof Response) {
                return $pageData;
            }
            return $this->renderPage($request, $response, $pageData);
        }

        // Not cached: fetch from CMS.
        if ($collection !== null) {
            $page = $this->cmsClient->getCollectionItem($collection, $slug);
        } else {
            $page = $this->cmsClient->getPage($slug);
        }

        if ($page) {
            // Valid page: process and store using unique key.
            $pageData = $this->dataProcessor->processPage($page, $pageType, $request);
            $this->cacheService->set($uniqueKey, $pageData);
            return $this->renderPage($request, $response, $pageData);
        }

        // Otherwise, it's a 404: retrieve (or store) under the standard key.
        $pageData = $this->cacheService->fetch($shared404Key);

        if (is_null($pageData)) {
            $pageData = $this->get404Data($request);
            $this->cacheService->set($shared404Key, $pageData);
        }

        return $this->renderPage($request, $response, $pageData, '404.twig')->withStatus(404);
    }

    /**
     * Helper method to render data in a 'page.twig' template.
     */
    private function renderPage(Request $request, Response $response, array $data, ?string $template = null): Response
    {
        // Check if 'json' parameter is set to true
        $queryParams = $request->getQueryParams();
        if (isset($queryParams['json']) && $queryParams['json'] === 'true') {
            $jsonData = [
                'metadata' => $data['metadata'] ?? [],
                'modules' => $data['modules'] ?? [],
                'globals' => array_merge($data['globals'] ?? [], $this->context->getGlobalContext()),
                'home_url'  => (empty($this->context->getLanguage())) ? '/' : '/' . $this->context->getLanguage(),
                'translations' => $data['translations'] ?? [],
                'paths' => $data['paths'] ?? [],
            ];
            $payload = json_encode($jsonData, JSON_PRETTY_PRINT);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        }

        $routesConfig = $request->getAttribute('routesConfig') ?? [];
        $collection = $request->getAttribute('collection') ?? null;

        if (empty($template)) {
            $template = 'page.twig';
            foreach ($routesConfig as $config) {
                if (isset($config['collection']) && $config['collection'] === $collection && isset($config['page'])) {
                    $template = 'pages/' . $config['page'] . '.twig';
                    break;
                }
            }
        }

        // Check if the request is an AJAX request
        $isAjax = $request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest';

        $html = $this->view->fetch($template, [
            'metadata' => $data['metadata'] ?? [],
            'modules' => $data['modules'] ?? [],
            'globals' => array_merge($data['globals'] ?? [], $this->context->getGlobalContext()),
            'translations' => $data['translations'] ?? [],
            'home_url'  => (empty($this->context->getLanguage())) ? '/' : '/' . $this->context->getLanguage(),
            'paths' => $data['paths'] ?? [],
            'isAjax' => $isAjax,
        ]);

        // Check if performance measurement is enabled
        if (isset($_ENV['MEASURE_PERFORMANCE']) && $_ENV['MEASURE_PERFORMANCE'] == 'true' && defined('START_TIME')) {
            // Calculate the elapsed time
            $elapsedTime = microtime(true) - START_TIME;
            $html .= "\n<!-- Page generated in " . round($elapsedTime, 4) . " seconds -->";
        }

        // Update the HTML using HtmlUpdater
        $htmlUpdater = new HtmlUpdater($html);
        $updatedHtml = $htmlUpdater->updateHtml();

        // Write the updated HTML to the response
        $response->getBody()->write($updatedHtml);
        return $response;
    }

    /**
     * Helper method to render error pages.
     */

    private function get404Data(Request $request): array
    {
        // Fetch data for the 404 page - globals etc.
        $data = $this->dataProcessor->processPage([], '404', $request);

        return $data;
    }
}
