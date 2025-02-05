<?php

namespace App\Modules\Manager;

use GuzzleHttp\Promise\Utils;
use GuzzleHttp\Promise\Create;
use App\Modules\Manager\ModuleProcessorInterface;
use App\Utils\ApiFetcher;
use App\Services\CacheService;
use App\CmsClients\CmsClientInterface;

class ModuleProcessorManager
{
    private array $processors = [];
    private ApiFetcher $apiFetcher;
    private CacheService $cacheService;
    private CmsClientInterface $cmsClient;

    public function __construct(ApiFetcher $apiFetcher, CacheService $cacheService, CmsClientInterface $cmsClient)
    {
        $this->apiFetcher = $apiFetcher;
        $this->cacheService = $cacheService;
        $this->cmsClient = $cmsClient;
    }

    public function processModules(array $modules, $language): array
    {
        $promises = [];

        foreach ($modules as &$module) {
            $type = $module['type'] ?? null;
            if ($type && !isset($this->processors[$type])) {
                $this->loadProcessor($type);
            }

            if ($type && isset($this->processors[$type]) && method_exists($this->processors[$type], 'fetchData')) {
                $processor = $this->processors[$type];
                // fetchData returns an array containing one or more Guzzle promises
                $dataArray = $processor->fetchData($module, $this->apiFetcher);

                // We'll convert each entry in $dataArray into a cached promise
                $cachedArray = [];
                foreach ($dataArray as $key => $guzzlePromise) {
                    $cacheKey = 'module_'.$type.'_'.$key.'_'.md5(json_encode($module));
                    $existingCachedValue = $this->cacheService->get($cacheKey, fn() => false);

                    if ($existingCachedValue !== false) {
                        // Already cached: create a resolved promise
                        $cachedArray[$key] = Create::promiseFor($existingCachedValue);
                    } else {
                        // Not in cache: use Guzzle promise, then store result
                        $cachedArray[$key] = $guzzlePromise->then(function ($response) use ($cacheKey) {
                            $data = json_decode($response->getBody()->getContents(), true);
                            // Save to cache
                            $this->cacheService->get($cacheKey, fn() => $data);
                            return $data;
                        });
                    }
                }

                // Wrap the final array of (cached) promises in Utils::all
                $promises[$type] = Utils::all($cachedArray);
            }
        }

        // Wait for all promises to complete
        $results = Utils::settle($promises)->wait();

        // Process modules with the resolved data
        foreach ($modules as &$module) {
            $type = $module['type'] ?? null;
            if ($type && isset($this->processors[$type])) {
                $resolvedData = $results[$type]['value'] ?? [];

                // Process the resolved data with the CMS client
                $resolvedData = $this->cmsClient->processData($resolvedData, $language);

                // Process the module with the resolved data
                $module = $this->processors[$type]->process($module, $resolvedData);
            }
        }

        return $modules;
    }

    private function loadProcessor(string $type): void
    {
        $processorFile = BASE_PATH . '/application/modules/m_' . $type . '.php';
        if (file_exists($processorFile)) {
            require_once $processorFile;
            $className = 'App\\Modules\\m_' . $type;
            if (class_exists($className)) {
                $processor = new $className();
                if ($processor instanceof ModuleProcessorInterface) {
                    $this->processors[$type] = $processor;
                }
            }
        }
    }
}