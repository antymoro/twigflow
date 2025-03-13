<?php

namespace App\CmsClients;

interface CmsClientInterface
{
    public function getPages(): array;
    public function getDocumentsUrls($jobs): array;
    public function getPage(string $slug): ?array;
    public function processData(array $modules, array $globalsConfig, array $data): ?array;
    public function getCollectionItem(string $collection, string $slug): ?array;
    public function urlBuilder(string $baseUrl, string $query, array $options): string;
}