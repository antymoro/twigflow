<?php

namespace App\CmsClients;

abstract class AbstractCmsClient implements CmsClientInterface
{
    protected function updateGlobals(array &$asyncData, array $globalsConfig): void
    {
        $globals = array_keys($globalsConfig);

        foreach ($asyncData['globals'] as $key => $value) {
            if (in_array($key, $globals) && !empty($value['result'])) {
                $asyncData['globals'][$key] = $value['result'];
            }
        }
    }

    protected function combineData(array $modules, array $asyncData): array
    {
        return [
            'modules' => $modules,
            'modulesAsyncData' => $asyncData['modulesAsyncData'] ?? [],
            'globals' => $asyncData['globals'] ?? [],
            'metadata' => $asyncData['metadata'] ?? []
        ];
    }

    protected function updateModulesAsyncData(array &$processedCombined): void
    {
        foreach ($processedCombined['modulesAsyncData'] as $index => $module) {
            foreach ($module as $key => $value) {
                if (!empty($value['result'])) {
                    $processedCombined['modulesAsyncData'][$index][$key] = $value['result'];
                }
            }
        }
    }

    protected function extractProcessedData(array $processedCombined): array
    {
        return [
            'modules' => $processedCombined['modules'] ?? [],
            'modulesAsyncData' => $processedCombined['modulesAsyncData'] ?? [],
            'globals' => $processedCombined['globals'] ?? [],
            'metadata' => $processedCombined['metadata'] ?? []
        ];
    }
}
