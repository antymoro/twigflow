<?php

namespace App\Processors;

use GuzzleHttp\Promise\Utils;
use App\Utils\ApiFetcher;
use App\Services\CacheService;
use App\CmsClients\CmsClientInterface;
use App\Modules\Manager\ModuleProcessorInterface;
use App\Pages\Manager\PageProcessorInterface;

class DataProcessor
{
    private ApiFetcher $apiFetcher;
    private CmsClientInterface $cmsClient;
    private array $processors = [];
    private $pageProcessor;

    public function __construct(
        ApiFetcher $apiFetcher,
        CacheService $cacheService,
        CmsClientInterface $cmsClient
    ) {
        $this->apiFetcher   = $apiFetcher;
        $this->cmsClient    = $cmsClient;
    }

    public function processPage(array $pageData, string $pageType, ?string $language): array
    {
        // Step 1: Separate metadata and modules from the page data
        $metadata = $pageData['result'] ?? [];
        $modules = $pageData['modules'] ?? [];
        unset($metadata['modules']);

        // Step 2: Collect promises for the page and the modules
        $promises = $this->collectPromises($modules, $language, $pageType, $metadata);

        // Step 3: Collect global promises
        $globalsConfig = json_decode(file_get_contents(BASE_PATH . '/application/globals.json'), true);
        foreach ($globalsConfig as $key => $global) {
            $promises[$key] = $this->fetchGlobal($global['query']);
        }

        // Step 4: Wait for all promises to resolve
        $results = Utils::settle($promises)->wait();
        $results = $this->flattenResults($results);

        // Step 5: Tidy up the results
        $results['globals'] = [];
        foreach ($globalsConfig as $key => $global) {
            $results['globals'][$key] = $results[$key] ?? [];
            unset($results[$key]);
        }

        $results['metadata'] = array_merge($metadata, $results['page'] ?? []);
        unset($results['page']);
        $results['modulesAsyncData'] = [];

        foreach ($results as $key => $result) {
            if (strpos($key, 'module_') === 0) {
                $index = intval(str_replace('module_', '', $key));
                $results['modulesAsyncData'][$index] = $result;
                unset($results[$key]);
            }
        }

        // Step 6: Process the data using the CMS client
        $pageData = $this->cmsClient->processData($modules, $globalsConfig, $results, $language);
        $modules = $this->processModules($pageData, $language);
        $pageData['modules'] = $modules;

        // Step 7: Add translations
        $staticTranslations = json_decode(file_get_contents(BASE_PATH . '/application/translations.json'), true);
        $pageData['translations'] = $this->parseTranslations($staticTranslations, $language);

        foreach ($globalsConfig as $key => $global) {
            $pageData['globals'][$key] = $pageData['globals'][$key] ?? [];
        }

        // Step 8: Process the page using the page processor if available
        if (isset($this->pageprocessor)) {
            $pageData['metadata'] = $this->pageProcessor->process($pageData['metadata'], $pageData['metadata']);
        }

        return $pageData;
    }

    private function collectPromises(array $modules, ?string $language, string $pageType, array $metadata): array
    {
        $promises = [];
        $index = 0;

        if (!isset($this->pageprocessor)) {
            $this->loadPageProcessor($pageType);
        }

        if (isset($this->pageProcessor) && method_exists($this->pageProcessor, 'fetchData')) {
            $dataArray = $this->pageProcessor->fetchData($metadata, $this->apiFetcher, []);
            $promises['page'] = Utils::all($dataArray);
        }

        foreach ($modules as $module) {
            $type = $module['type'] ?? null;
            if (!$type) {
                $index++;
                continue;
            }

            if (!isset($this->processors[$type])) {
                $this->loadModuleProcessors($type);
            }

            if (isset($this->processors[$type]) && method_exists($this->processors[$type], 'fetchData')) {
                $processor = $this->processors[$type];
                $dataArray = $processor->fetchData($module, $this->apiFetcher, []);
                $promises['module_' . $index] = Utils::all($dataArray);
            }

            $index++;
        }

        return $promises;
    }

    private function flattenResults(array $results): array
    {
        $flattened = [];
        foreach ($results as $key => $result) {
            if (isset($result['value'])) {
                $value = $result['value'];
                if ($value instanceof \Psr\Http\Message\ResponseInterface) {
                    $content = $value->getBody()->getContents();
                    $decoded = json_decode($content, true);
                    $flattened[$key] = $decoded !== null ? $decoded : $content;
                } else {
                    $flattened[$key] = $value;
                }
            } elseif (isset($result['reason'])) {
                $flattened[$key] = $result['reason']->getMessage();
            }
        }
        return $flattened;
    }

    private function processModules(array $pageData, ?string $language): array
    {
        $modules = $pageData['modules'] ?? [];
        $index = 0;
        foreach ($modules as &$module) {
            $type = $module['type'] ?? null;
            if ($type && isset($this->processors[$type])) {
                $moduleAsyncData = [];
                if (isset($pageData['modulesAsyncData'][$index])) {
                    $moduleAsyncData = $pageData['modulesAsyncData'][$index];
                }
                $module = $this->processors[$type]->process($module, $moduleAsyncData);
            }
            $index++;
        }
        return $modules;
    }

    private function loadModuleProcessors(string $type): void
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

    private function loadPageProcessor(string $type): void
    {
        $processorFile = BASE_PATH . '/application/pages/' . $type . '.php';
        if (file_exists($processorFile)) {
            require_once $processorFile;
            $className = 'App\\Pages\\' . $type;
            if (class_exists($className)) {
                $processor = new $className();
                if ($processor instanceof PageProcessorInterface) {
                    $this->pageProcessor = $processor;
                }
            }
        }
    }

    private function parseTranslations(array $translations, ?string $locale): array
    {
        $parsedTranslations = [];
        foreach ($translations as $key => $translation) {
            if (isset($translation[$locale])) {
                $parsedTranslations[$key] = $translation[$locale];
            } else {
                $parsedTranslations[$key] = $translation['en'] ?? '';
            }
        }
        return $parsedTranslations;
    }

    private function fetchGlobal(string $query)
    {
        return $this->apiFetcher->asyncFetchFromApi($query);
    }
}