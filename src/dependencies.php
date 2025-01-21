<?php

namespace App;

use Slim\App;
use Slim\Views\Twig;
use Twig\Loader\FilesystemLoader;
use App\Services\PayloadService;
use App\Services\CacheService;
use App\Controllers\PageController;

return function (App $app) {
    $container = $app->getContainer();

    if ($container === null) {
        throw new \Exception("Container is not set.");
    }

    // Register Twig Loader
    $container->set(\Twig\Loader\LoaderInterface::class, function() {
        return new FilesystemLoader(__DIR__ . '/../templates');
    });

    // Register Twig
    $container->set(Twig::class, function($c) {
        $loader = $c->get(\Twig\Loader\LoaderInterface::class);
        return new Twig($loader, ['cache' => false]); // Disable cache for development
    });

    // Alias 'view' to Twig
    $container->set('view', function($c) {
        return $c->get(Twig::class);
    });

    // Register CacheService
    $container->set(CacheService::class, function() {
        return new CacheService();
    });

    // Register PayloadService
    $container->set(PayloadService::class, function($c) {
        return new PayloadService($_ENV['PAYLOAD_API_URL'], $c->get(CacheService::class));
    });

    // Register PageController
    $container->set(PageController::class, function($c) {
        return new PageController(
            $c->get(Twig::class),
            $c->get(PayloadService::class)
        );
    });
};