<?php

use DI\ContainerBuilder;
use App\Services\CacheService;
use App\CmsClients\CmsClientInterface;
use App\CmsClients\PayloadCmsClient;
use App\Controllers\PageController;
use App\Controllers\CacheController;
use App\Modules\Manager\ModuleProcessorManager;
use Slim\Views\Twig;
use Twig\Loader\FilesystemLoader;

return [
    // Register Twig Loader
    \Twig\Loader\LoaderInterface::class => function() {
        return new FilesystemLoader(__DIR__ . '/../templates');
    },

    // Register Twig
    Twig::class => function($c) {
        $loader = $c->get(\Twig\Loader\LoaderInterface::class);
        return new Twig($loader, ['cache' => false]); // Disable cache for development
    },

    // Alias 'view' to Twig
    'view' => function($c) {
        return $c->get(Twig::class);
    },

    // Register CacheService
    CacheService::class => \DI\create(CacheService::class),

    // Register CmsClientInterface
    CmsClientInterface::class => function($c) {
        $cmsClient = $_ENV['CMS_CLIENT'] ?? 'payload';
        $apiUrl = $_ENV['PAYLOAD_API_URL'];
        $cacheService = $c->get(CacheService::class);

        switch ($cmsClient) {
            case 'payload':
                return new PayloadCmsClient($apiUrl, $cacheService);
            // Add other CMS clients here
            default:
                throw new \Exception("Unsupported CMS client: $cmsClient");
        }
    },

    // Register ModuleProcessorManager
    ModuleProcessorManager::class => \DI\create(ModuleProcessorManager::class),

    // Register PageController
    PageController::class => function($c) {
        return new PageController(
            $c->get(Twig::class),
            $c->get(CmsClientInterface::class),
            $c->get(ModuleProcessorManager::class)
        );
    },

    // Register CacheController
    CacheController::class => function($c) {
        return new CacheController($c->get(CacheService::class));
    },
];