<?php

namespace App\CmsClients;

interface CmsClientInterface {
    public function getPages(): array;
    public function getPage(string $slug, ?string $language = null): ?array;
}