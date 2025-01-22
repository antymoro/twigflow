<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use DI\ContainerBuilder;
use Slim\Factory\AppFactory;
use Slim\Views\TwigMiddleware;
use Slim\Middleware\ErrorMiddleware;
use Dotenv\Dotenv;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/src/Utils/helpers.php';

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Determine environment
$environment = $_ENV['APP_ENV'] ?? 'production';
$displayErrorDetails = $environment === 'development';
$logErrors = true;
$logErrorDetails = true;

// Create Container using PHP-DI
$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions(__DIR__ . '/src/dependencies.php');
$container = $containerBuilder->build();

// Set the container to create App with
AppFactory::setContainer($container);
$app = AppFactory::create();

// Add Routing Middleware
$app->addRoutingMiddleware();

// Create a logger
$logger = new Logger('app');
$logFile = __DIR__ . '/logs/app.log';
if (!file_exists(dirname($logFile))) {
    mkdir(dirname($logFile), 0777, true);
}
$logger->pushHandler(new StreamHandler($logFile, Logger::DEBUG));

// Add Error Middleware (displayErrorDetails, logErrors, logErrorDetails)
$errorMiddleware = $app->addErrorMiddleware($displayErrorDetails, $logErrors, $logErrorDetails, $logger);

// Add Twig Middleware
$twig = $container->get('view');
$app->add(TwigMiddleware::create($app, $twig));

// Define a simple home route (for testing)
$app->get('/', function ($request, $response, $args) {
    $response->getBody()->write("Hello world!");
    return $response;
});

// Load application routes using the route loader function
(require __DIR__ . '/src/routes.php')($app);

// Run the application
$app->run();