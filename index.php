<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use DI\Container;
use Slim\Factory\AppFactory;
use Slim\Views\TwigMiddleware;
use Slim\Middleware\ErrorMiddleware;
use Dotenv\Dotenv;

function dd($data) {
    var_dump($data);
    die();
}

require __DIR__ . '/vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Determine environment
$environment = $_ENV['APP_ENV'] ?? 'production';
$displayErrorDetails = $environment === 'development';
$logErrors = $environment !== 'production';
$logErrorDetails = $environment !== 'production';

// Create Container using PHP-DI
$container = new Container();

// Set the container to create App with
AppFactory::setContainer($container);
$app = AppFactory::create();

// Add Routing Middleware
$app->addRoutingMiddleware();

// Add Error Middleware (displayErrorDetails, logErrors, logErrorDetails)
$errorMiddleware = $app->addErrorMiddleware($displayErrorDetails, $logErrors, $logErrorDetails);

// Register dependencies
(require __DIR__ . '/src/dependencies.php')($app);

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