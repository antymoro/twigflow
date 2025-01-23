<?php

namespace App;

use Slim\App;
use App\Middleware\LanguageMiddleware;

return function (App $app) {
    // Load supported languages from environment
    $supportedLanguages = explode(',', $_ENV['SUPPORTED_LANGUAGES'] ?? '');
    $defaultLanguage = $supportedLanguages[0] ?? 'en';

    // Route to clear cache without language prefix
    $app->get('/clear-cache', \App\Controllers\CacheController::class . ':clearCache')
        ->setName('cache.clear');

    if (!empty($supportedLanguages[0])) {
        // Add Language Middleware
        $app->add(new LanguageMiddleware($supportedLanguages, $defaultLanguage));

        // Route to handle dynamic pages using the PageController with slug and language prefix
        $app->get('/{language}/{slug}', \App\Controllers\PageController::class . ':show')
            ->setName('page.show');
    } else {
        // Route to handle dynamic pages using the PageController with slug without language prefix
        $app->get('/{slug}', \App\Controllers\PageController::class . ':show')
            ->setName('page.show');
    }
};
