<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use App\CmsClients\CmsClientInterface;
use App\Utils\HtmlUpdater;
use App\Processors\PageProcessor;
use App\Services\CacheService;

class PageController
{
    private Twig $view;
    private CmsClientInterface $cmsClient;
    private string $templatePath;
    private string $userTemplatePath;
    private PageProcessor $pageProcessor;
    private CacheService $cacheService;

    /**
     * Constructor to initialize dependencies.
     *
     * @param Twig $view
     * @param PageProcessor $pageProcessor
     * @param CmsClientInterface $cmsClient
     * @param CacheService $cacheService
     * @param string $templatePath
     */
    public function __construct(Twig $view, PageProcessor $pageProcessor, CmsClientInterface $cmsClient, CacheService $cacheService, string $templatePath = 'src/views/')
    {
        $this->view = $view;
        $this->pageProcessor = $pageProcessor;
        $this->cmsClient = $cmsClient;
        $this->cacheService = $cacheService;
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
        $language = $request->getAttribute('language') ?? null;

        return $this->handlePageRequest($request, $response, $slug, $language);
    }

    /**
     * Show a collection item, determined by the "collection" attribute and "slug" argument.
     */
    public function showCollectionItem(Request $request, Response $response, array $args): Response
    {
        $collection = $request->getAttribute('collection');
        $slug = $args['slug'] ?? null;
        $language = $request->getAttribute('language') ?? null;

        if (!$collection || !$slug) {
            return $this->renderError($response, 404, 'Collection or slug not specified');
        }

        return $this->handlePageRequest($request, $response, $slug, $language, $collection);
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
    private function handlePageRequest(Request $request, Response $response, string $slug, ?string $language, ?string $collection = null): Response
    {
        // Include $collection in the cache key for uniqueness
        $cacheKey = 'page_' . md5($slug . $language . ($collection ?? ''));
        
        // Cache processed data
        $pageData = $this->cacheService->get($cacheKey, function () use ($slug, $language, $collection) {
            if ($collection !== null) {
                $page = $this->cmsClient->getCollectionItem($collection, $slug, $language);
            } else {
                $page = $this->cmsClient->getPage($slug, $language);
            }
            if (!$page) {
                throw new \Exception('Page not found');
            }
            return $this->pageProcessor->processPage($page, $language);
        });
    
        return $this->renderPage($request, $response, $pageData, $language);
    }

    /**
     * Helper method to render data in a 'page.twig' template.
     */
    private function renderPage(Request $request, Response $response, array $data, ?string $language): Response
    {
        // Check if 'json' parameter is set to true
        $queryParams = $request->getQueryParams();
        if (isset($queryParams['json']) && $queryParams['json'] === 'true') {
            $jsonData = [
                'metadata' => $data['metadata'] ?? [],
                'modules' => $data['modules'] ?? [],
                'globals' => $data['globals'] ?? [],
                'translations' => $data['translations'] ?? []
            ];
            $payload = json_encode($jsonData, JSON_PRETTY_PRINT);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        }

        $routesConfig = $request->getAttribute('routesConfig') ?? [];
        $collection = $request->getAttribute('collection') ?? null;

        $template = 'page.twig';
        foreach ($routesConfig as $config) {
            if (isset($config['collection']) && $config['collection'] === $collection && isset($config['page'])) {
                $template = 'pages/' . $config['page'] . '.twig';
                break;
            }
        }

        // Render the Twig template with data
        $html = $this->view->fetch($template, [
            'metadata' => $data['metadata'] ?? [],
            'modules' => $data['modules'] ?? [],
            'globals' => $data['globals'] ?? [],
            'translations' => $data['translations'] ?? [],
            'home_url'  => (empty($language)) ? '/' : '/'.$language,
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
    private function renderError(Response $response, int $statusCode, string $message): Response
    {
        return $this->view->render($response->withStatus($statusCode), '404.twig', [
            'message' => $message
        ]);
    }
}