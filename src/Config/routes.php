<?php

namespace App;

use Slim\App;
use App\Middleware\LanguageMiddleware;
use App\Controllers\ScraperController;
use App\Controllers\SearchController;
use App\Controllers\ApiController;
use App\Context\RequestContext;

return function (App $app) use ($container) {

    // Get the RequestContext object from the container
    $context = $container->get(RequestContext::class);

    // Load supported languages from environment
    $supportedLanguages = array_filter(explode(',', $_ENV['SUPPORTED_LANGUAGES'] ?? ''));
    $defaultLanguage = $supportedLanguages[0] ?? 'en';

    $context->setSupportedLanguages($supportedLanguages);

    // Add Language Middleware
    $app->add(new LanguageMiddleware($supportedLanguages, $defaultLanguage, $context));

    // Load routing configuration from JSON file
    $routesConfig = json_decode(file_get_contents(BASE_PATH . '/application/routes.json'), true);
    if (empty($routesConfig)) $routesConfig = [];

    // Define a dynamic route for the homepage
    $app->get('/', \App\Controllers\PageController::class . ':showHomepage')
        ->setName('page.showHomepage');

    // Route to clear cache without language prefix
    $app->get('/api/clear-cache', \App\Controllers\CacheController::class . ':clearCache')
        ->setName('cache.clear');

    // Scraper Routes
    $app->get('/api/scraper/init', [ScraperController::class, 'initQueue'])
        ->setName('scraper.initQueue');

    $app->get('/api/scraper/process', [ScraperController::class, 'processQueue'])
        ->setName('scraper.processQueue');

    $app->get('/api/scraper/prune', [ScraperController::class, 'pruneIndex'])
        ->setName('scraper.pruneIndex');

    $app->get('/api/scraper/reset', [ScraperController::class, 'resetQueue'])
        ->setName('scraper.resetQueue');

    // Route to handle search requests
    $app->get('/api/search', SearchController::class . ':search')
        ->setName('search');

    $app->get('/api/live-search', SearchController::class . ':liveSearch')
        ->setName('liveSearch');

    // Dynamic route for API endpoints
    $app->map(['GET', 'POST'], '/api/{endpoint}', function ($request, $response, $args) use ($container) {
        $apiFetcher = $container->get(\App\Utils\ApiFetcher::class);
        $controller = new ApiController($apiFetcher);
        return $controller->handle($request, $response, $args);
    })->setName('api.handle');

    // Route to handle dynamic pages using the PageController with slug
    $app->get('/{slug}', \App\Controllers\PageController::class . ':show')
        ->setName('page.show');

    // Define routes for collections
    foreach ($routesConfig as $pattern => $config) {
        $collection = $config['collection'];
        $app->get($pattern, \App\Controllers\PageController::class . ':showCollectionItem')
            ->setName('page.showCollectionItem')
            ->add(function ($request, $handler) use ($collection, $routesConfig) {
                return $handler->handle($request->withAttribute('collection', $collection)->withAttribute('routesConfig', $routesConfig));
            });
    }

    $app->get('/{routes:.+}', \App\Controllers\PageController::class . ':show')
        ->setName('page.catchAll');
};
