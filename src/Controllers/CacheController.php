<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Services\CacheService;

class CacheController
{
    private CacheService $cacheService;

    /**
     * Constructor to initialize the CacheService dependency.
     *
     * @param CacheService $cacheService
     */
    public function __construct(CacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    /**
     * Clear the cache.
     *
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function clearCache(Request $request, Response $response, array $args): Response
    {
        $this->cacheService->clearAll();

        $twigCacheDir = BASE_PATH . '/cache/twig';
        $this->clearTwigCache($twigCacheDir);

        $response->getBody()->write("Cache cleared successfully.");
        return $response;
    }

    private function clearTwigCache(string $cacheDir): void
    {
        if (is_dir($cacheDir)) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($cacheDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($files as $fileinfo) {
                $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
                $todo($fileinfo->getRealPath());
            }

            rmdir($cacheDir);
        }
    }
}
