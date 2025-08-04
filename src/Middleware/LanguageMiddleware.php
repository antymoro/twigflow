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
        if (empty($this->supportedLanguages) || strtoupper($request->getMethod()) === 'POST') {
            // No languages defined, proceed without modification
            return $handler->handle($request);
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $uri = $request->getUri();
        $path = $uri->getPath();
        $segments = explode('/', trim($path, '/'));

        // Extract the current language from the URL
        $currentLanguage = $segments[0] ?? $this->defaultLanguage;

        // Ensure the current language is in the array of supported languages
        if (!in_array($currentLanguage, $this->supportedLanguages)) {
            $currentLanguage = $this->defaultLanguage;
        }

        // Check for the ?lang=true query parameter
        $queryParams = $request->getQueryParams();
        if (isset($queryParams['lang']) && $queryParams['lang'] === 'true') {
            // Save the current language as preferred in the session
            $_SESSION['preferred_language'] = $currentLanguage;
        }

        // Fetch the preferred language from the session
        if (isset($_SESSION['preferred_language']) && in_array($_SESSION['preferred_language'], $this->supportedLanguages)) {
            $this->defaultLanguage = $_SESSION['preferred_language'];
        } else {
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
        }

        if (empty($segments[0]) || !in_array($segments[0], $this->supportedLanguages)) {
            // Redirect to the same URL with the default language prefix
            $newPath = '/' . $this->defaultLanguage . $path;
            $response = new \Slim\Psr7\Response();

            // Use 301 for bots (SEO), 302 for users (UX flexibility)
            $statusCode = $this->isBot($request) ? 301 : 302;

            return $response->withHeader('Location', $newPath)->withStatus($statusCode);
        }

        $language = array_shift($segments);
        $request = $request->withAttribute('language', $language);
        $uri = $uri->withPath('/' . implode('/', $segments));
        $request = $request->withUri($uri);

        // Set the language in the context
        $this->context->setLanguage($language);

        return $handler->handle($request);
    }

    /**
     * Detect if the request is from a search engine bot
     */
    private function isBot(ServerRequestInterface $request): bool
    {
        $userAgent = strtolower($request->getHeaderLine('User-Agent'));

        $botPatterns = [
            'googlebot',
            'bingbot',
            'slurp',      // Yahoo
            'duckduckbot',
            'baiduspider',
            'yandexbot',
            'facebookexternalhit',
            'twitterbot',
            'linkedinbot',
            'whatsapp',
            'telegrambot'
        ];

        foreach ($botPatterns as $pattern) {
            if (strpos($userAgent, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }
}
