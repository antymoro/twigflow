<?php

namespace App\Modules\Manager;

use GuzzleHttp\Promise\Utils;
use App\Modules\Manager\ModuleProcessorInterface;
use App\Utils\ApiFetcher;
use App\Services\CacheService;

class ModuleProcessorManager
{
    private array $processors = [];
    private ApiFetcher $apiFetcher;
    private CacheService $cacheService;

    public function __construct(ApiFetcher $apiFetcher, CacheService $cacheService)
    {
        $this->apiFetcher = $apiFetcher;
        $this->cacheService = $cacheService;
    }

    public function processModules(array $modules): array
    {
        $promises = [];

        foreach ($modules as &$module) {
            $type = $module['type'] ?? null;
            if ($type && !isset($this->processors[$type])) {
                $this->loadProcessor($type);
            }

            if ($type && isset($this->processors[$type])) {
                $processor = $this->processors[$type];
                $dataArray = $processor->fetchData($module, $this->apiFetcher);

                $promises[$type] = Utils::all($dataArray)->then(function ($resolvedArray) {
                    foreach ($resolvedArray as $key => $response) {
                        if ($response instanceof \Psr\Http\Message\ResponseInterface) {
                            $resolvedArray[$key] = json_decode($response->getBody()->getContents(), true);
                        }
                    }
                    return $resolvedArray;
                });
            }
        }

        // Wait for all promises to complete
        $results = Utils::settle($promises)->wait();

        // Process modules with the resolved data
        foreach ($modules as &$module) {
            $type = $module['type'] ?? null;
            if ($type && isset($this->processors[$type])) {
                // $results[$type]['value'] is the resolved array from Utils::all
                $module = $this->processors[$type]->process($module, $results[$type]['value'] ?? []);
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