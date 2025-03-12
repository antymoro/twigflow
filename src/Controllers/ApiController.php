<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Utils\ApiFetcher;

class ApiController
{
    protected $apiFetcher;

    public function __construct(ApiFetcher $apiFetcher)
    {
        $this->apiFetcher = $apiFetcher;
    }

    public function handle(Request $request, Response $response, array $args): Response
    {
        $endpoint = $args['endpoint'];
        $filePath = BASE_PATH . '/application/api/' . $endpoint . '.php';

        if (file_exists($filePath)) {
            require_once $filePath;

            $className = '\\App\\Api\\' . ucfirst($endpoint);
            if (class_exists($className)) {
                $apiInstance = new $className($this->apiFetcher);
                if (method_exists($apiInstance, 'process')) {
                    $result = $apiInstance->process($request, $response, $args);
                    $response->getBody()->write(json_encode($result));
                    return $response->withHeader('Content-Type', 'application/json');
                } else {
                    $response->getBody()->write(json_encode(['error' => 'Method process not found']));
                    return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
                }
            } else {
                $response->getBody()->write(json_encode(['error' => 'Class not found']));
                return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
            }
        } else {
            $response->getBody()->write(json_encode(['error' => 'Endpoint not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }
    }
}