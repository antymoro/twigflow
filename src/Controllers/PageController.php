<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use App\Services\PayloadService;

class PageController {
    private Twig $view;
    private PayloadService $payloadService;

    public function __construct(Twig $view, PayloadService $payloadService) {
        $this->view = $view;
        $this->payloadService = $payloadService;
    }

    public function show(Request $request, Response $response, array $args): Response {
        error_log("PageController::show() method called");

        $slug = $args['slug'] ?? null;
        $language = $request->getAttribute('language') ?? null;

        if (!$slug) {
            return $this->view->render($response->withStatus(400), '404.twig', [
                'message' => 'Invalid page slug'
            ]);
        }

        $page = $this->payloadService->getPage($slug, $language);

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