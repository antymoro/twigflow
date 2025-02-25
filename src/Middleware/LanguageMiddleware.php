<?php

namespace App\Middleware;

use App\Context\RequestContext;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class LanguageMiddleware
{
    private array $supportedLanguages;
    private string $defaultLanguage;
    private RequestContext $context;

    public function __construct(array $supportedLanguages, string $defaultLanguage, RequestContext $context)
    {
        $this->supportedLanguages = $supportedLanguages;
        $this->defaultLanguage = $defaultLanguage;
        $this->context = $context;
    }

    public function __invoke(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (empty($this->supportedLanguages)) {
            // No languages defined, proceed without modification
            return $handler->handle($request);
        }

        // Determine the default language based on the browser's preferred language
        $acceptLanguageHeader = $request->getHeaderLine('Accept-Language');
        if ($acceptLanguageHeader) {
            $preferredLanguages = explode(',', $acceptLanguageHeader);
            foreach ($preferredLanguages as $preferredLanguage) {
                $lang = substr($preferredLanguage, 0, 2); // Extract the language code
                if (in_array($lang, $this->supportedLanguages)) {
                    $this->defaultLanguage = $lang;
                    break;
                }
            }
        }

        $uri = $request->getUri();
        $path = $uri->getPath();
        $segments = explode('/', trim($path, '/'));

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

        // Set the language in the context
        $this->context->setLanguage($language);

        return $handler->handle($request);
    }
}