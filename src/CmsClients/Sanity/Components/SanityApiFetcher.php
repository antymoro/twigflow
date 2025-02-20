<?php

namespace App\CmsClients\Sanity\Components;

use App\Utils\ApiFetcher;

class SanityApiFetcher
{
    private string $apiUrl;
    private ApiFetcher $apiFetcher;

    public function __construct(string $apiUrl)
    {
        $this->apiUrl = rtrim($apiUrl, '/');
        $this->apiFetcher = new ApiFetcher($this->apiUrl);
    }

    public function fetchQuery(string $query, $options=[]): ?array
    {
        return $this->apiFetcher->fetchFromApi($query, $options);
    }
}