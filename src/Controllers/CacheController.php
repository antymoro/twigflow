<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Services\CacheService;

class CacheController {
    private CacheService $cacheService;

    public function __construct(CacheService $cacheService) {
        $this->cacheService = $cacheService;
    }

    public function clearCache(Request $request, Response $response, array $args): Response {
        $this->cacheService->clearAll();
        $response->getBody()->write("Cache cleared successfully.");
        return $response;
    }
}