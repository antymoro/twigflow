<?php

namespace App;

use Slim\App;
use App\Middleware\LanguageMiddleware;

return function (App $app) {
    // Load supported languages from environment
    $supportedLanguages = array_filter(explode(',', $_ENV['SUPPORTED_LANGUAGES'] ?? ''));
    $defaultLanguage = $supportedLanguages[0] ?? 'en';

    // Add Language Middleware
    $app->add(new LanguageMiddleware($supportedLanguages, $defaultLanguage));

    // Load routing configuration from JSON file
    $routesConfig = json_decode(file_get_contents(BASE_PATH . '/application/routes.json'), true);

    // Define a dynamic route for the homepage
    $app->get('/', \App\Controllers\PageController::class . ':showHomepage')
        ->setName('page.showHomepage');

    // Route to clear cache without language prefix
    $app->get('/clear-cache', \App\Controllers\CacheController::class . ':clearCache')
        ->setName('cache.clear');

    // Route to handle dynamic pages using the PageController with slug
    $app->get('/{slug}', \App\Controllers\PageController::class . ':show')
        ->setName('page.show');

    // Define routes for collections
    foreach ($routesConfig as $pattern => $config) {
        $collection = $config['collection'];
        $app->get($pattern, \App\Controllers\PageController::class . ':showCollectionItem')
            ->setName('page.showCollectionItem')
            ->add(function ($request, $handler) use ($collection) {
                return $handler->handle($request->withAttribute('collection', $collection));
            });
    }
};
