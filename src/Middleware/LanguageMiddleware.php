<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class LanguageMiddleware {
    private array $supportedLanguages;
    private string $defaultLanguage;

    public function __construct(array $supportedLanguages, string $defaultLanguage) {
        $this->supportedLanguages = $supportedLanguages;
        $this->defaultLanguage = $defaultLanguage;
    }

    public function __invoke(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
        $uri = $request->getUri();
        $path = $uri->getPath();
        $segments = explode('/', trim($path, '/'));

        if (empty($this->supportedLanguages)) {
            // No languages defined, proceed without modification
            return $handler->handle($request);
        }

        if (empty($segments[0]) || !in_array($segments[0], $this->supportedLanguages)) {
            // Redirect to the same URL with the default language prefix
            $newPath = '/' . $this->defaultLanguage . $path;
            $response = new \Slim\Psr7\Response();
            return $response->withHeader('Location', $newPath)->withStatus(302);
        }

        $language = array_shift($segments);
        $request = $request->withAttribute('language', $language);
        $uri = $uri->withPath('/' . implode('/', $segments));
        $request = $request->withUri($uri);

        return $handler->handle($request);
    }
}