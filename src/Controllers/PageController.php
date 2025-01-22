<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use App\CmsClients\CmsClientInterface;

class PageController {
    private Twig $view;
    private CmsClientInterface $cmsClient;

    public function __construct(Twig $view, CmsClientInterface $cmsClient) {
        $this->view = $view;
        $this->cmsClient = $cmsClient;
    }

    public function show(Request $request, Response $response, array $args): Response {

        $slug = $args['slug'] ?? null;
        $language = $request->getAttribute('language') ?? null;

        if (!$slug) {
            return $this->view->render($response->withStatus(400), '404.twig', [
                'message' => 'Invalid page slug'
            ]);
        }

        $page = $this->cmsClient->getPage($slug, $language);

        if (!$page) {
            return $this->view->render($response->withStatus(404), '404.twig', [
                'message' => 'Page not found'
            ]);
        }

        return $this->view->render($response, 'page.twig', [
            'page' => $page
        ]);
    }
}