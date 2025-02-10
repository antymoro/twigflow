<?php

namespace App\CmsClients;

interface CmsClientInterface
{
    public function getPages(): array;
    public function getPage(string $slug, ?string $language = null): ?array;
    public function processData(array $modules, array $globalsConfig, array $data, ?string $language): ?array;
    public function getCollectionItem(string $collection, string $slug, ?string $language): ?array;
}
