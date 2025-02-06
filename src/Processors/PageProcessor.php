<?php

namespace App\Processors;

use GuzzleHttp\Promise\Utils;
use GuzzleHttp\Promise\Create;
use App\Utils\ApiFetcher;
use App\Services\CacheService;
use App\CmsClients\CmsClientInterface;
use App\Modules\Manager\ModuleProcessorInterface;

class PageProcessor
{
    private ApiFetcher $apiFetcher;
    private CacheService $cacheService;
    private CmsClientInterface $cmsClient;
    private array $processors = [];

    public function __construct(
        ApiFetcher $apiFetcher,
        CacheService $cacheService,
        CmsClientInterface $cmsClient
    ) {
        $this->apiFetcher   = $apiFetcher;
        $this->cacheService = $cacheService;
        $this->cmsClient    = $cmsClient;
    }

    /**
     * Process the page by collecting promises, resolving references, 
     * and processing modules.
     *
     * @param array $pageData
     * @param string|null $language
     * @return array
     */
    public function processPage(array $pageData, ?string $language): array
    {
        $modules = $pageData['modules'] ?? [];

        // Step 1: Collect all promises for modules and globals.
        $promises = $this->collectPromises($modules, $language);

        // Step 2: Wait for all promises to settle.
        $results = Utils::settle($promises)->wait();
        $results = $this->flattenResults($results);

        // Step 4: Process each module with the resolved data.
        $pageData = $this->cmsClient->processData($modules, $results, $language);


        // Step 6: Process each module via its processor.
        $modules = $this->processModules($pageData['modules'], $language);

        $pageData['modules']   = $modules;

        return $pageData;
    }

    /**
     * Collect all promises for modules and globals.
     *
     * @param array $modules
     * @param string|null $language
     * @return array
     */
    private function collectPromises(array $modules, ?string $language): array
    {
        $promises = [];

        // Use module type as key: duplicate modules of the same type reuse the same promise.
        foreach ($modules as $module) {
            $type = $module['type'] ?? null;
            if ($type) {
                if (!isset($this->processors[$type])) {
                    $this->loadProcessor($type);
                }
                if (isset($this->processors[$type]) && method_exists($this->processors[$type], 'fetchData')) {
                    // Only add a promise for this type once.
                    if (!isset($promises[$type])) {
                        $processor = $this->processors[$type];
                        $dataArray = $processor->fetchData($module, $this->apiFetcher);
                        $cachedArray = [];
                        foreach ($dataArray as $dataKey => $guzzlePromise) {
                            $cacheKey = 'module_' . $type . '_' . $dataKey;
                            $existingValue = $this->cacheService->get($cacheKey, fn() => false);
                            if ($existingValue !== false) {
                                $cachedArray[$dataKey] = Create::promiseFor($existingValue);
                            } else {
                                $cachedArray[$dataKey] = $guzzlePromise->then(function ($response) use ($cacheKey) {
                                    $data = json_decode($response->getBody()->getContents(), true);
                                    // Save to cache using the same logic as ModuleProcessorManager.
                                    $this->cacheService->get($cacheKey, fn() => $data);
                                    return $data;
                                });
                            }
                        }
                        $promises[$type] = Utils::all($cachedArray);
                    }
                }
            }
        }

        // // Add global promise for navigation.
        $promises['globals'] = $this->fetchNavigation();

        return $promises;
    }

    /**
     * Collect references from the settled promises.
     *
     * @param array $results
     * @return array
     */
    private function collectReferences(array $results): array
    {
        $references = [];
        foreach ($results as $result) {
            if (isset($result['value']['_ref'])) {
                $references[] = $result['value']['_ref'];
            }
        }
        return $references;
    }

    /**
     * Process each module using its processor.
     *
     * @param array $modules
     * @param string|null $language
     * @return array
     */
    private function processModules(array $modules, ?string $language): array
    {
        foreach ($modules as &$module) {
            $type = $module['type'] ?? null;
            if ($type && isset($this->processors[$type])) {
                $module = $this->processors[$type]->process($module);
            }
        }
        return $modules;
    }

    /**
     * Fetch global navigation asynchronously.
     *
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    private function fetchNavigation()
    {
        $query = '*[_type == "menu"]';
        $url = $_ENV['API_URL'] . '/data/query/production?query=' . urlencode($query);
        return $this->apiFetcher->asyncFetchFromApi($url);
    }

    /**
     * Collect global data from settled promises.
     *
     * @param array $results
     * @return array
     */
    private function collectGlobalData(array $results): array
    {
        $globals = [];
        if (isset($results['global_navigation']['value'])) {
            $globals['navigation'] = $results['global_navigation']['value'];
        }
        return $globals;
    }

    /**
     * Load a processor by module type.
     *
     * @param string $type
     */
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
}
