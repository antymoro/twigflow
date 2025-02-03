<?php

namespace App\Modules\Manager;

use GuzzleHttp\Promise\Utils;
use App\Modules\Manager\ModuleProcessorInterface;
use App\Utils\ApiFetcher;

class ModuleProcessorManager
{
    private array $processors = [];
    private ApiFetcher $apiFetcher;

    public function __construct(ApiFetcher $apiFetcher)
    {
        $this->apiFetcher = $apiFetcher;
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
                $promises[$type] = $this->processors[$type]->fetchData($module, $this->apiFetcher);
            }
        }

        // wait for all promises to complete
        $results = Utils::settle($promises)->wait();

        // process the modules with the fetched data
        foreach ($modules as &$module) {
            $type = $module['type'] ?? null;
            if ($type && isset($this->processors[$type])) {
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