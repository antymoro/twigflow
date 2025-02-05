<?php

namespace App\CmsClients;

interface CmsClientInterface
{
    public function getPages(): array;
    public function getPage(string $slug, ?string $language = null): ?array;
    public function getScaffold(string $global): ?array;
    public function processData(array $array): ?array;
    public function getCollectionItem(string $collection, string $slug, ?string $language): ?array;
}
