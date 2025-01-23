<?php

// Display errors for development
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

// Load environment variables from .env file
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Determine environment (default to 'production' if not set)
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

// Define the log directory and file pattern
$logDir = __DIR__ . '/logs';
$logFilePattern = $logDir . '/app.log';

// Ensure the log directory exists
if (!file_exists($logDir)) {
    mkdir($logDir, 0777, true);
}

// Use RotatingFileHandler to create a new log file each day
$logger->pushHandler(new \Monolog\Handler\RotatingFileHandler($logFilePattern, 0, Logger::DEBUG));

// Add Error Middleware (displayErrorDetails, logErrors, logErrorDetails)
$errorMiddleware = $app->addErrorMiddleware($displayErrorDetails, $logErrors, $logErrorDetails, $logger);

// Add Twig Middleware for rendering templates
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