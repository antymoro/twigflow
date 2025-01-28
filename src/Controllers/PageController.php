<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use App\CmsClients\CmsClientInterface;
use App\Modules\Manager\ModuleProcessorManager;

class PageController
{
    private Twig $view;
    private CmsClientInterface $cmsClient;
    private ModuleProcessorManager $moduleProcessorManager;

    /**
     * Constructor to initialize dependencies.
     *
     * @param Twig $view
     * @param CmsClientInterface $cmsClient
     * @param ModuleProcessorManager $moduleProcessorManager
     */
    public function __construct(Twig $view, CmsClientInterface $cmsClient, ModuleProcessorManager $moduleProcessorManager)
    {
        $this->view = $view;
        $this->cmsClient = $cmsClient;
        $this->moduleProcessorManager = $moduleProcessorManager;
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

        return $this->renderPage($response, $page);
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
        // $language can be retrieved from the request if needed
        $language = null; 
        // $language = $request->getAttribute('language') ?? null;

        if (!$collection || !$slug) {
            return $this->renderError($response, 400, 'Invalid collection or slug');
        }

        // Fetch content based on collection and slug
        $content = $this->cmsClient->getCollectionItem($collection, $slug, $language);

        if (!$content) {
            return $this->renderError($response, 404, 'Content not found');
        }

        // Example logic for adding the collection as a module
        $module = array_merge($content, ['type' => $collection]);
        $content['modules'][] = $module;

        return $this->renderPage($response, $content);
    }

    /**
     * Helper method to render data in a 'page.twig' template.
     */
    private function renderPage(Response $response, array $data): Response
    {
        if (isset($data['modules']) && is_array($data['modules'])) {
            $data['modules'] = $this->moduleProcessorManager->processModules($data['modules']);
        }

        $scaffold = $this->getScaffold();
        return $this->view->render($response, 'page.twig', [
            'modules' => $data['modules'] ?? [],
            'scaffold' => $scaffold,
        ]);
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

    /**
     * Fetch global data such as header and footer.
     */
    private function getScaffold(): array
    {
        $scaffold = [];
        $scaffold['header'] = $this->cmsClient->getScaffold('header');
        $scaffold['footer'] = $this->cmsClient->getScaffold('footer');
        return $scaffold;
    }
}