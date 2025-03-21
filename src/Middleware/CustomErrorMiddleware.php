<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Slim\Exception\HttpException;
use Slim\Handlers\ErrorHandler;
use Slim\Views\Twig;

class CustomErrorMiddleware extends ErrorHandler
{
    private Twig $twig;

    // Inject Twig via the constructor
    public function __construct(
        $callableResolver,
        $responseFactory,
        Twig $twig,
        ?\Throwable $previous = null
    ) {
        $this->twig = $twig;
        parent::__construct($callableResolver, $responseFactory, $previous);
    }

    protected function respond(): ResponseInterface
    {
        $exception = $this->exception;

        // Create a custom response
        $response = $this->responseFactory->createResponse();

        // Determine the status code
        $statusCode = $exception instanceof HttpException
            ? $exception->getCode()
            : 500;

        // Log error details for internal use
        error_log($exception->getMessage());
        error_log($exception->getTraceAsString());

        // For JSON requests
        $acceptHeader = $this->request->getHeaderLine('Accept');
        if (strpos($acceptHeader, 'application/json') !== false) {
            $response->getBody()->write(json_encode([
                'error' => true,
                'message' => $statusCode === 404
                    ? 'The requested resource was not found.'
                    : 'An unexpected error occurred. Please try again later.',
            ]));

            return $response->withHeader('Content-Type', 'application/json')
                            ->withStatus($statusCode);
        }

        // Render the Twig error template for non-JSON requests
        $errorMessage = $statusCode === 404
            ? 'The page you are looking for does not exist.'
            : 'An unexpected error occurred. Please try again later.';

        // Render template file (for example, "error.twig")
        $html = $this->twig->fetch('error.twig', [
            'message' => $errorMessage,
            'statusCode' => $statusCode,
        ]);

        $response->getBody()->write($html);

        return $response->withHeader('Content-Type', 'text/html')
                        ->withStatus($statusCode);
    }
}