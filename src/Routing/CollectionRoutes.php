<?php

namespace App\Routing;

class CollectionRoutes
{
    /**
     * Parse routes.json and return a map of collection types to their paths.
     * Always includes 'page' with an empty path.
     *
     * @return array<string, array{path: string}>
     */
    public static function getCollections(): array
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

        return $collections;
    }
}
