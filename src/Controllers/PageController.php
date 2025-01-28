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
     *
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $slug = $args['slug'] ?? null;
        $language = $request->getAttribute('language') ?? null;

        // Validate the slug
        if (!$slug) {
            return $this->view->render($response->withStatus(400), '404.twig', [
                'message' => 'Invalid page slug'
            ]);
        }

        // Fetch the page data
        $page = $this->cmsClient->getPage($slug, $language);

        // Handle page not found
        if (!$page) {
            return $this->view->render($response->withStatus(404), '404.twig', [
                'message' => 'Page not found'
            ]);
        }

        // Process the modules
        if (isset($page['modules']) && is_array($page['modules'])) {
            $page['modules'] = $this->moduleProcessorManager->processModules($page['modules']);
        }

        // Fetch global data
        $scaffold = $this->getScaffold();

        // Render the page with the combined data
        return $this->view->render($response, 'page.twig', [
            'modules' => $page['modules'] ?? [],
            'scaffold' => $scaffold,
        ]);
    }

    /**
     * Fetch global data such as header and footer.
     *
     * @return array
     */
    private function getScaffold(): array
    {
        $scaffold = [];
        $scaffold['header'] = $this->cmsClient->getScaffold('header');
        $scaffold['footer'] = $this->cmsClient->getScaffold('footer');
        return $scaffold;
    }
}
