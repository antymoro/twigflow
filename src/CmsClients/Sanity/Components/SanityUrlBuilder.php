<?php

namespace App\CmsClients\Sanity\Components;

class SanityUrlBuilder
{
    public function createCollectionUrls($data, array $collections, ?string $language = null)
    {
        if (is_array($data)) {
            if (isset($data['_type']) && isset($collections[$data['_type']]) && isset($data['slug']['current'])) {
                $slug = $data['slug']['current'];
                $urlPrefix = $language ? '/' . $language : '';
                $data['url'] = $urlPrefix . $collections[$data['_type']]['path'] . '/' . $slug;
            }

            foreach ($data as $key => $value) {
                $data[$key] = $this->createCollectionUrls($value, $collections, $language);
            }
        }
        return $data;
    }
}