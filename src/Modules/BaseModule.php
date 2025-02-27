<?php

namespace App\Modules;

use Psr\Http\Message\ServerRequestInterface as Request;
use App\Context\RequestContext;
use App\Utils\ApiFetcher;
use GuzzleHttp\Promise\PromiseInterface;
use App\Repositories\ContentRepository;

class BaseModule
{
    protected ApiFetcher $apiFetcher;
    protected Request $request;
    protected RequestContext $context;
    protected ContentRepository $contentRepository;

    public function __construct(ApiFetcher $apiFetcher, Request $request, RequestContext $context, ContentRepository $contentRepository)
    {
        $this->apiFetcher = $apiFetcher;
        $this->request = $request;
        $this->context = $context;
        $this->contentRepository = $contentRepository;
    }

    public function getQuery(?string $key = null): array|string
    {
        $queryParams = $this->request->getQueryParams();
        return $key === null ? $queryParams : ($queryParams[$key] ?? '');
    }

    public function fetchFromApi(string $query): PromiseInterface
    {
        return $this->apiFetcher->asyncFetchFromApi($query);
    }

    public function fetch(string $url): PromiseInterface
    {
        return $this->apiFetcher->asyncFetch($url);
    }

    public function getLanguage(): string
    {
        return $this->context->getLanguage();
    }

    public function search(string $query): PromiseInterface
    {
        return $this->fetchOwnApiData('/api/search', ['q' => $query]);
    }

    public function fetchOwnApiData(string $endpoint, array $params = []): PromiseInterface
    {
        $query = http_build_query($params);
        $url = $this->getBaseUrl() . '/' . ltrim($endpoint, '/') . '?' . $query;
        return $this->fetch($url);
    }

    public function getCurrentUrl(): string
    {
        $uri = $this->request->getUri();
        $scheme = $uri->getScheme();
        $host = $uri->getHost();
        $port = $uri->getPort();
        $path = $uri->getPath();
        $query = $uri->getQuery();

        $url = $scheme . '://' . $host;
        if ($port) {
            $url .= ':' . $port;
        }
        $url .= $path;
        if ($query) {
            $url .= '?' . $query;
        }

        $language = $this->getLanguage();
        if ($language) {
            $url .= '/' . $language;
        }

        return $url;
    }

    public function getBaseUrl(): string
    {
        $uri = $this->request->getUri();
        $scheme = $uri->getScheme();
        $host = $uri->getHost();
        $port = $uri->getPort();

        $url = $scheme . '://' . $host;
        if ($port) {
            $url .= ':' . $port;
        }

        $language = $this->getLanguage();
        if ($language) {
            $url .= '/' . $language;
        }

        return $url;
    }
}