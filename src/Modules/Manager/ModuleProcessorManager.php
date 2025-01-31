<?php

namespace App\Modules\Manager;

use App\Modules\Manager\ModuleProcessorInterface;

class ModuleProcessorManager
{
    private array $processors = [];

    public function processModules(array $modules): array
    {
        foreach ($modules as &$module) {
            $type = $module['type'] ?? null;
            if ($type && !isset($this->processors[$type])) {
                $this->loadProcessor($type);
            }
            if ($type && isset($this->processors[$type])) {
                $module = $this->processors[$type]->process($module);
            }
        }
        return $modules;
    }

    private function loadProcessor(string $type): void
    {
        $processorFile = __DIR__ . '/../../../resources/modules/m_' . $type . '.php';
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