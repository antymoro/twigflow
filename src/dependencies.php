<?php

use App\Services\CacheService;
use App\CmsClients\CmsClientInterface;
use App\CmsClients\PayloadCmsClient;
use App\CmsClients\SanityCmsClient;
use App\Controllers\PageController;
use App\Controllers\CacheController;
use App\Modules\Manager\ModuleProcessorManager;
use Slim\Views\Twig;
use Twig\Loader\FilesystemLoader;
use Twig\Extension\DebugExtension;
use Dotenv\Dotenv;

/**
 * This file is used to define and register dependencies for the application.
 * It uses PHP-DI (Dependency Injection) to manage and inject dependencies.
 * The return value is an array of definitions that map interfaces or classes
 * to their respective implementations.
 */

// Load environment variables from .env file
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Determine environment (default to 'production' if not set)
$environment = $_ENV['APP_ENV'] ?? 'production';
$debug = $environment === 'development';

// Get the cache setting from the environment variable
$cacheEnabled = filter_var($_ENV['TWIG_CACHE'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
// Set the cache directory based on the environment variable
$cacheDir = $cacheEnabled ? __DIR__ . '/../cache' : false;

return [
    // Register Twig Loader
    \Twig\Loader\LoaderInterface::class => function () {
        // FilesystemLoader is used to load Twig templates from the specified directory
        return new FilesystemLoader(__DIR__ . '/../templates');
    },

    // Register Twig
    Twig::class => function ($c) use ($cacheDir, $debug) {
        // Get the Twig Loader from the container
        $loader = $c->get(\Twig\Loader\LoaderInterface::class);
        // Create and return a new Twig instance with the loader and cache configuration
        $twig = new Twig($loader, [
            'cache' => $cacheDir,
            'debug' => $debug,
        ]);
        $twig->addExtension(new DebugExtension());
        return $twig;
    },

    // Alias 'view' to Twig
    'view' => function ($c) {
        // Alias the 'view' key to the Twig instance
        return $c->get(Twig::class);
    },

    // Register CacheService
    CacheService::class => \DI\create(CacheService::class),

    // Register CmsClientInterface
    CmsClientInterface::class => function ($c) {
        // Determine the CMS client to use based on environment variables
        $cmsClient = $_ENV['CMS_CLIENT'] ?? 'payload';
        $apiUrl = $_ENV['API_URL'];
        $cacheService = $c->get(CacheService::class);

        // Return the appropriate CMS client implementation
        switch (strtolower($cmsClient)) {
            case 'payload':
                return new PayloadCmsClient($apiUrl, $cacheService);
            case 'sanity':
                return new SanityCmsClient($apiUrl, $cacheService);
            default:
                throw new \Exception("Unsupported CMS client: $cmsClient");
        }
    },

    // Register ModuleProcessorManager
    ModuleProcessorManager::class => \DI\create(ModuleProcessorManager::class),

    // Register PageController
    PageController::class => function ($c) {
        // Create and return a new PageController instance with dependencies
        return new PageController(
            $c->get(Twig::class),
            $c->get(CmsClientInterface::class),
            $c->get(ModuleProcessorManager::class)
        );
    },

    // Register CacheController
    CacheController::class => function ($c) {
        // Create and return a new CacheController instance with dependencies
        return new CacheController($c->get(CacheService::class));
    },
];
