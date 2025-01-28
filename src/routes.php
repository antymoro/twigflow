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
    $routesConfig = json_decode(file_get_contents(__DIR__ . '/../config/routes.json'), true);

    // Define a dynamic route for the homepage
    $app->get('/', \App\Controllers\PageController::class . ':showHomepage')
        ->setName('page.showHomepage');

    // Route to handle dynamic pages using the PageController with slug
    $app->get('/{slug}', \App\Controllers\PageController::class . ':show')
    ->setName('page.show');

    // Define routes for collections
    foreach ($routesConfig['collections'] as $collection => $pattern) {
        $app->get($pattern, \App\Controllers\PageController::class . ':showCollectionItem')
            ->setName('page.showCollectionItem')
            ->add(function ($request, $handler) use ($collection) {
                return $handler->handle($request->withAttribute('collection', $collection));
            });
    }

};