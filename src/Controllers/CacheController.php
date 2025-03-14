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

        $configPath = BASE_PATH . '/application/cache_regeneration.json';
        $regeneratedPaths = [];
        if (file_exists($configPath)) {
            $config = json_decode(file_get_contents($configPath), true);
            $paths = $config['regeneration_paths'] ?? [];
    
            $baseUrl = $this->getBaseUrl($request);
            foreach ($paths as $path) {
                $url = rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
                $this->regenerateCache($url);
                $regeneratedPaths[] = $url;
            }
        }
    
        $responseData = [
            'status' => 'success',
            'message' => 'Cache cleared successfully.',
            'regenerated_urls' => $regeneratedPaths,
        ];
    
        $response->getBody()->write(json_encode($responseData, JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json');
    }


    private function regenerateCache(string $url): void
    {
        try {
            $client = new \GuzzleHttp\Client();
            $client->getAsync($url, ['http_errors' => false])->then(
                function ($response) use ($url) {
                    if ($response->getStatusCode() !== 200) {
                        error_log("Cache regeneration request to {$url} returned status code: " . $response->getStatusCode());
                    }
                },
                function ($exception) use ($url) {
                    error_log("Failed to regenerate cache for URL {$url}: " . $exception->getMessage());
                }
            );
        } catch (\Exception $e) {
            error_log("Failed to initiate async cache regeneration for URL {$url}: " . $e->getMessage());
        }
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

    private function getBaseUrl($request): string
    {
        $uri = $request->getUri();
        $scheme = $uri->getScheme();
        $host = $uri->getHost();
        $port = $uri->getPort();

        $url = $scheme . '://' . $host;
        if ($port) {
            $url .= ':' . $port;
        }

        return $url;
    }
}
