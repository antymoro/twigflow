<?php

namespace App\Modules\Manager;

use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use Symfony\Component\Filesystem\Filesystem;

class ModuleProcessorManager
{
    private string $modulePath;
    private string $userModulePath;
    private Client $client;
    private Filesystem $filesystem;

    public function __construct(Client $client, string $modulePath = 'src/Modules/', string $userModulePath = 'resources/modules/')
    {
        $this->client = $client;
        $this->modulePath = $modulePath;
        $this->userModulePath = $userModulePath;
        $this->filesystem = new Filesystem();
    }

    public function initializeModules(array $modules): array
    {
        foreach ($modules as &$module) {
            $module = $this->processModule($module, 'queue');
        }

        return $modules;
    }

    public function finalizeModules(array $modules): array
    {
        foreach ($modules as &$module) {
            $module = $this->processModule($module, 'process');
        }

        return $modules;
    }

    private function processModule(array $module, string $phase): array
    {
        $moduleType = strtolower($module['_type']);
        $userModuleClass = $this->userModulePath . 'm_' . $moduleType . '.php';
        $defaultModuleClass = $this->modulePath . 'm_' . $moduleType . '.php';

        if ($this->filesystem->exists($userModuleClass)) {
            require_once $userModuleClass;
            $className = 'App\\Modules\\m_' . ucfirst($moduleType);
        } elseif ($this->filesystem->exists($defaultModuleClass)) {
            require_once $defaultModuleClass;
            $className = 'App\\Modules\\m_' . ucfirst($moduleType);
        } else {
            return $module;
        }

        if (class_exists($className)) {
            $processor = new $className($this->client);
            if ($processor instanceof ModuleProcessorInterface) {
                return $processor->$phase($module);
            }
        }

        return $module;
    }
}