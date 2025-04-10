<?php

use App\Services\CacheService;
use App\Services\DatabaseService;
use App\Services\ScraperService;

use App\CmsClients\CmsClientInterface;
use App\CmsClients\Payload\PayloadCmsClient;
use App\CmsClients\Sanity\SanityCmsClient;

use App\Modules\BaseModule;

use App\Controllers\PageController;
use App\Processors\DataProcessor;

use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\ServerRequestCreatorFactory; 
use App\Context\RequestContext;

use App\Controllers\CacheController;
use App\Controllers\ScraperController;
use App\Controllers\SearchController;
use App\Controllers\ApiController;

use App\Repositories\ContentRepository;
use App\Utils\ApiFetcher;

use Slim\Views\Twig;
use Twig\Loader\FilesystemLoader;
use Twig\Extension\DebugExtension;

use App\Middleware\CustomErrorMiddleware;
use Slim\CallableResolver;
use Slim\Interfaces\CallableResolverInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Slim\Psr7\Factory\ResponseFactory;


/**
 * This file is used to define and register dependencies for the application.
 * It uses PHP-DI (Dependency Injection) to manage and inject dependencies.
 * The return value is an array of definitions that map interfaces or classes
 * to their respective implementations.
 */

// Determine environment (default to 'production' if not set)
$environment = $_ENV['APP_ENV'] ?? 'production';
$debug = $environment === 'development';

// Get the cache setting from the environment variable
$cacheEnabled = filter_var($_ENV['TWIG_CACHE'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
// Set the cache directory based on the environment variable
$cacheDir = $cacheEnabled ? BASE_PATH . '/cache/twig' : false;

return [

    CallableResolverInterface::class => \DI\create(CallableResolver::class),
    ResponseFactoryInterface::class => \DI\create(\Slim\Psr7\Factory\ResponseFactory::class),

    // Register Twig Loader
    \Twig\Loader\LoaderInterface::class => function () {
        // FilesystemLoader is used to load Twig templates from the specified directory
        return new FilesystemLoader(BASE_PATH . '/application/views');
    },

    // Register CustomErrorMiddleware
    CustomErrorMiddleware::class => function ($container) {
        return new CustomErrorMiddleware(
            $container->get(CallableResolverInterface::class),
            $container->get(ResponseFactoryInterface::class),
            $container->get(Twig::class)
        );
    },

    // Register Twig
    Twig::class => function ($container) use ($cacheDir, $debug) {
        // Get the Twig Loader from the container
        $loader = $container->get(\Twig\Loader\LoaderInterface::class);
        // Create and return a new Twig instance with the loader and cache configuration
        $twig = new Twig($loader, [
            'cache' => $cacheDir,
            'debug' => $debug,
        ]);
        $twig->addExtension(new DebugExtension());
        return $twig;
    },

    // Alias 'view' to Twig
    'view' => function ($container) {
        // Alias the 'view' key to the Twig instance
        return $container->get(Twig::class);
    },

    // Register CacheService
    CacheService::class => \DI\create(CacheService::class),

    ApiFetcher::class => function () {
        $baseUri = $_ENV['API_URL'];
        $cacheService = new CacheService();
        
        // default URL builder that will be replaced later
        $defaultUrlBuilder = function($baseUrl, $query, $options) {
            return rtrim($baseUrl, '/') . '/' . ltrim($query, '/');
        };
        
        return new ApiFetcher($baseUri, $cacheService, $defaultUrlBuilder);
    },

    // Register CmsClientInterface
    CmsClientInterface::class => function ($container) {
        $cmsClient = $_ENV['CMS_CLIENT'] ?? 'payload';
        $apiUrl = $_ENV['API_URL'];
        $apiFetcher = $container->get(ApiFetcher::class);
        $context = $container->get(RequestContext::class);
        
        // Create the CMS client
        $client = null;
        switch (strtolower($cmsClient)) {
            case 'payload':
                $client = new PayloadCmsClient($apiUrl, $context, $apiFetcher);
                break;
            case 'sanity':
                $client = new SanityCmsClient($apiUrl, $context, $apiFetcher);
                break;
            default:
                throw new \Exception("Unsupported CMS client: $cmsClient");
        }
        
        $urlBuilder = function($baseUrl, $query, $options) use ($client) {
            return $client->urlBuilder($baseUrl, $query, $options);
        };
        
        // update the URL builder in the ApiFetcher
        $apiFetcher->setUrlBuilder($urlBuilder);
        
        return $client;
    },

    RequestContext::class => function () {
        $language = $_ENV['DEFAULT_LANGUAGE'] ?? 'en';
        return new RequestContext($language);
    },

    Request::class => function () {
        return ServerRequestCreatorFactory::create()->createServerRequestFromGlobals();
    },

    BaseModule::class => function ($container) {
        return new BaseModule(
            $container->get(ApiFetcher::class),
            $container->get(Request::class),
            $container->get(RequestContext::class),
            $container->get(ContentRepository::class)
        );
    },

    // Register DataProcessor
    DataProcessor::class => function ($container) {
        return new DataProcessor(
            $container->get(ApiFetcher::class),
            $container->get(CmsClientInterface::class),
            $container->get(RequestContext::class),
            $container->get(BaseModule::class)
        );
    },

    // Register PageController
    PageController::class => function ($container) {
        // Create and return a new PageController instance with dependencies
        return new PageController(
            $container->get(Twig::class),
            $container->get(DataProcessor::class),
            $container->get(CmsClientInterface::class),
            $container->get(CacheService::class),
            $container->get(RequestContext::class)
        );
    },

    // Register CacheController
    CacheController::class => function ($container) {
        // Create and return a new CacheController instance with dependencies
        return new CacheController($container->get(CacheService::class));
    },

    // Register ApiController
    ApiController::class => function ($container) {
        // Create and return a new CacheController instance with dependencies
        return new CacheController($container->get(ApiFetcher::class),);
    },

    // Register DatabaseService
    DatabaseService::class => \DI\create(DatabaseService::class),

    // Register ContentRepository
    ContentRepository::class => function ($container) {
        return new ContentRepository($container->get(DatabaseService::class)->getConnection());
    },

    // Register ScraperService
    ScraperService::class => function ($container) {
        return new ScraperService(
            $container->get(ContentRepository::class),
            $container->get(CmsClientInterface::class)
        );
    },

    // Register ScraperController
    ScraperController::class => function ($container) {
        return new ScraperController(
            $container->get(CmsClientInterface::class),
            $container->get(ScraperService::class),
            $container->get(ApiFetcher::class)
        );
    },

    SearchController::class => function ($container) {
        return new SearchController(
            $container->get(ContentRepository::class),
            $container->get(CmsClientInterface::class)
        );
    },
];