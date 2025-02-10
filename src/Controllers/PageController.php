<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use App\CmsClients\CmsClientInterface;
use App\Utils\HtmlUpdater;
use App\Processors\PageProcessor;

class PageController
{
    private Twig $view;
    private CmsClientInterface $cmsClient;
    private string $templatePath;
    private string $userTemplatePath;
    private PageProcessor $pageProcessor;

    /**
     * Constructor to initialize dependencies.
     *
     * @param Twig $view
     * @param CmsClientInterface $cmsClient
     * 
     */
    public function __construct(Twig $view, PageProcessor $pageProcessor, CmsClientInterface $cmsClient, string $templatePath = 'src/views/')
    {
        $this->view = $view;
        $this->pageProcessor = $pageProcessor;
        $this->cmsClient = $cmsClient;
        $this->templatePath = $templatePath;
        $this->userTemplatePath = BASE_PATH . '/application/views/';
    }

    /**
     * Show a page based on the provided slug and language.
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $slug = $args['slug'] ?? null;
        $language = $request->getAttribute('language') ?? null;

        if (!$slug) {
            return $this->renderError($response, 400, 'Invalid page slug');
        }

        // Fetch the page data
        $page = $this->cmsClient->getPage($slug, $language);

        if (!$page) {
            return $this->renderError($response, 404, 'Page not found');
        }

        $page = $this->pageProcessor->processPage($page, $language);

        return $this->renderPage($request, $response, $page, $language);
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
     * Show a collection item, determined by the "collection" attribute and "slug" argument.
     */
    public function showCollectionItem(Request $request, Response $response, array $args): Response
    {
        $collection = $request->getAttribute('collection');
        $slug = $args['slug'] ?? null;
        $language = $request->getAttribute('language') ?? null;

        if (!$collection || !$slug) {
            return $this->renderError($response, 400, 'Invalid collection or slug');
        }

        // Fetch content based on collection and slug
        $page = $this->cmsClient->getCollectionItem($collection, $slug, $language);

        if (!$page) {
            return $this->renderError($response, 404, 'Page not found');
        }

        $page = $this->pageProcessor->processPage($page, $language);

        return $this->renderPage($request, $response, $page, $language);
    }

    /**
     * Helper method to render data in a 'page.twig' template.
     */
    private function renderPage(Request $request, Response $response, array $data, string $language): Response
    {

        // Check if 'json' parameter is set to true
        $queryParams = $request->getQueryParams();
        if (isset($queryParams['json']) && $queryParams['json'] === 'true') {
            $jsonData = [
                'modules' => $data['modules'] ?? [],
                'globals' => $data['globals'] ?? [],
            ];
            $payload = json_encode($jsonData, JSON_PRETTY_PRINT);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        }

        $template = 'page.twig';
        if (file_exists($this->userTemplatePath . $template)) {
            $template = $template;
        } else {
            $template = $this->templatePath . $template;
        }

         // Render the Twig template with data
         $html = $this->view->fetch($template, [
            'modules' => $data['modules'] ?? [],
            'globals' => $data['globals'] ?? [],
            'translations' => $data['translations'] ?? [],
            'home_url'  => (empty($language)) ? '/' : '/'.$language,
        ]);

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
