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

        if (
            isset($document['type']) && isset($this->collections[$document['type']])
            && isset($document['slug']) && !str_contains($document['_id'], 'drafts')
        ) {
            $slug = $document['slug'];
            $urls = [];

            // If no supported languages are defined, assume it's a single-language site (default behavior)
            if (empty($supportedLanguages)) {
                $supportedLanguages = [''];
            }

            foreach ($supportedLanguages as $language) {
                $urlPrefix = $language ? '/' . $language : '';
                $url = $urlPrefix . $this->collections[$document['type']]['path'] . '/' . $slug;
                $urls[$language] = $url;
            }

            $documents[] = [
                'id' => $document['_id'],
                'title' => $document['title'],
                'urls' => $urls,
                'type' => $document['type'],
                'languages' => $supportedLanguages,
                'slug' => $slug,
                'document_id' => $document['document_id'],
                'created_at' => $document['created_at'],
                'updated_at' => $document['updated_at'],
            ];
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
