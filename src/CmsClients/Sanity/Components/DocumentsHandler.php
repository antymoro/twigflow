<?php

namespace App\CmsClients\Sanity\Components;

class DocumentsHandler
{
    private array $collections;

    public function __construct()
    {
        $this->initializeCollections();
    }

    public function prepareDocument($document, $supportedLanguages)
    {
        $documents = [];

        if (isset($document['_type']) && isset($this->collections[$document['_type']])
            && isset($document['slug']['current']) && !str_contains($document['_id'], 'drafts')) {
            $slug = $document['slug']['current'];
            foreach ($supportedLanguages as $language) {
                $urlPrefix = $language ? '/' . $language : '';
                $url = $urlPrefix . $this->collections[$document['_type']]['path'] . '/' . $slug;

                $documents[] = [
                    'url' => $url,
                    'type' => $document['_type'],
                    'language' => $language,
                    'slug' => $slug,
                    'cms_id' => $document['_id']
                ];
            }
        }

        return $documents;
    }

    private function initializeCollections(): void
    {
        $routesConfig = json_decode(file_get_contents(BASE_PATH . '/application/routes.json'), true);
        $collections = [];

        foreach ($routesConfig as $route => $config) {
            if (isset($config['collection'])) {
                $collectionType = $config['collection'];
                $cleanPath = str_replace('/{slug}', '', $route);
                $collections[$collectionType] = ['path' => $cleanPath];
            }
        }

        $collections['page'] = ['path' => ''];
        $this->collections = $collections;
    }
}