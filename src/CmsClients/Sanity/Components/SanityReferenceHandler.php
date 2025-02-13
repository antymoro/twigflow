<?php

namespace App\CmsClients\Sanity\Components;

class SanityReferenceHandler
{
    private SanityApiFetcher $apiFetcher;
    private array $routesConfig;
    private array $collections = [];

    public function __construct(SanityApiFetcher $apiFetcher, array $routesConfig)
    {
        $this->apiFetcher = $apiFetcher;
        $this->routesConfig = $routesConfig;
    }

    public function fetchReferences(array $referenceIds, ?string $language = null): array
    {
        if (empty($referenceIds)) {
            return [];
        }

        $refIds = array_values(array_unique($referenceIds));
        $refIdsString = '["' . implode('","', $refIds) . '"]';
        $query = '*[_id in ' . $refIdsString . ']{ _id, slug, _type, image, label, description, name, title }';
        $response = $this->apiFetcher->fetchQuery($query);
        $result = $response['result'] ?? [];  

        $mapping = [];
        foreach ($result as $doc) {
            $mapping[$doc['_id']] = $doc;
        }
        return $mapping;
    }

    public function substituteReferences($data, array $mapping, ?string $language = null)
    {

        if (is_array($data)) {
            if (isset($data['_type']) && $data['_type'] === 'reference' && isset($data['_ref']) && !empty($mapping)) {
                $resolvedData = $mapping[$data['_ref']] ?? $data;
                return $this->processMappedReferences($resolvedData, $mapping, $language);
            }

            if (isset($data['_type']) && $data['_type'] === 'sanity.imageAsset' && isset($data['_id'])) {
                $data = $this->constructImageUrl($data['_id']);
            }

            if (isset($data['_type']) && $data['_type'] === 'image' && isset($data['asset']['_ref'])) {
                $data['asset'] = $this->substituteReferences($data['asset'], $mapping, $language);
            }

            foreach ($data as $key => $value) {
                $data[$key] = $this->substituteReferences($value, $mapping, $language);
            }
        }
        return $data;
    }

    private function initializeCollections(): void
    {
        $collections = [];
        foreach ($this->routesConfig as $route => $config) {
            if (isset($config['collection'])) {
                $collectionType = $config['collection'];
                $cleanPath = str_replace('/{slug}', '', $route);
                $collections[$collectionType] = ['path' => $cleanPath];
            }
        }

        // dd($collections);
        $collections['page'] = ['path' => ''];
        $this->collections = $collections;
    }

    private function constructImageUrl(string $imageId): string
    {
        $filename = preg_replace('/^image-/', '', $imageId);
        $filename = preg_replace('/-(jpg|png|webp|svg)$/', '.$1', $filename);
        return 'https://cdn.sanity.io/images/isvajgup/production/' . $filename;
    }

    

    private function processMappedReferences($data, array $mapping, ?string $language = null)
    {
        if (is_array($data)) {
            if (isset($data['_type']) && $data['_type'] === 'sanity.imageAsset' && isset($data['_id'])) {
                $data = $this->constructImageUrl($data['_id']);
            }

            if (isset($data['_type']) && $data['_type'] === 'reference'
            && isset($data['_ref']) && str_contains($data['_ref'], 'image-') ) {
                $data = $this->constructImageUrl($data['_ref']);
            }

            if (isset($data['_type']) && $data['_type'] === 'image'
            && isset($data['asset']['_ref']) && str_contains($data['asset']['_ref'], 'image-') ) {
                $data = $this->constructImageUrl($data['asset']['_ref']);
            }

            if (is_array($data)) {
                foreach ($data as $key => $value) {
                    $data[$key] = $this->processMappedReferences($value, $mapping, $language);
                }
            }
        }
        return $data;
    }

    public function resolveReferenceUrls($data, ?string $language = null)
    {
        if (empty($this->collections)) {
            $this->initializeCollections();
        }

        if (is_array($data)) {
            if (isset($data['_type']) && isset($this->collections[$data['_type']]) && isset($data['slug']['current'])) {
                $slug = $data['slug']['current'];
                $urlPrefix = $language ? '/' . $language : '';
                $data['url'] = $urlPrefix . $this->collections[$data['_type']]['path'] . '/' . $slug;
            }

            foreach ($data as $key => $value) {
                $data[$key] = $this->resolveReferenceUrls($value, $language);
            }
        }

        return $data;
    }

    
}