<?php

namespace App\Processors;

use GuzzleHttp\Promise\Utils;
use App\Utils\ApiFetcher;
use App\CmsClients\CmsClientInterface;
use App\Modules\Manager\ModuleProcessorInterface;
use App\Pages\Manager\PageProcessorInterface;
use App\Context\RequestContext;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Modules\BaseModule;


class DataProcessor
{
    private ApiFetcher $apiFetcher;
    private CmsClientInterface $cmsClient;
    private array $processors = [];
    private $pageProcessor;
    private $baseProcessor;
    private string|null $language;
    private RequestContext $context;
    private BaseModule $universalModule;

    public function __construct(
        ApiFetcher $apiFetcher,
        CmsClientInterface $cmsClient,
        RequestContext $context,
        BaseModule $universalModule
    ) {
        $this->apiFetcher = $apiFetcher;
        $this->cmsClient  = $cmsClient;
        $this->language = $context->getLanguage();
        $this->context = $context;
        $this->universalModule = $universalModule;
    }

    public function processPage(array $pageData, string $pageType, Request $request): array
    {
        $this->language = $this->context->getLanguage();

        // Step 1: Separate metadata and modules
        [$metadata, $modules] = $this->separateData($pageData);

        // Step 2: Collect promises (modules and global)
        $promises = $this->collectModulePromises($modules, $pageType, $metadata);
        [$globalsConfig, $promises] = $this->collectGlobalPromises($promises);

        // Step 3: Wait for promises and tidy up the results
        $promisesResults = Utils::settle($promises)->wait();
        $promisesResults = $this->flattenPromisesResults($promisesResults);
        $promisesResults = $this->tidyPromiseResults($promisesResults, $globalsConfig, $metadata);

        // Step 4: Process the data using the CMS client and modules
        $pageData = $this->cmsClient->processData($modules, $globalsConfig, $promisesResults);
        $modules = $this->processModules($pageData);
        $pageData['modules'] = $modules;

        // Step 5: Add translations
        $pageData['translations'] = $this->addTranslations();

        // Step 6: Process the page using the page processor (if available)
        if (isset($this->pageProcessor)) {
            $pageData = $this->pageProcessor->process(
                $pageData,
                $pageData['metadata']
            );
        }

        if (isset($this->baseProcessor)) {
            $pageData = $this->baseProcessor->process(
                $pageData,
                $pageData['metadata']
            );
        }

        // Step 7: Generate paths
        $pageData['paths'] = $this->generatePaths($request);

        // Step 8: Ensure globals and OG tags are set
        foreach ($globalsConfig as $key => $global) {
            $pageData['globals'][$key] = $pageData['globals'][$key] ?? [];
        }

        $pageData['globals'] = array_merge($pageData['globals'], $this->context->getGlobalContext());

        $pageData['metadata'] = array_merge($pageData['metadata'], $this->context->getOgTags());

        return $pageData;
    }

    // Helper: Separate metadata and modules from page data
    private function separateData(array $pageData): array
    {
        $metadata = $pageData['result'] ?? [];
        $modules = $pageData['modules'] ?? [];
        unset($metadata['modules']);
        return [$metadata, $modules];
    }

    // Helper: Collect global promises and return globals config + updated promises
    private function collectGlobalPromises(array $promises): array
    {
        $globalsConfigPath = BASE_PATH . '/application/globals.json';
        if (file_exists($globalsConfigPath)) {
            $globalsConfig = json_decode(file_get_contents($globalsConfigPath), true);
            foreach ($globalsConfig as $key => $global) {
                $promises[$key] = $this->fetchGlobal($global['query']);
            }
        } else {
            $globalsConfig = [];
        }
        return [$globalsConfig, $promises];
    }

    // Helper: Tidy up promises results from all promises
    private function tidyPromiseResults(array $results, array $globalsConfig, array $metadata): array
    {
        // Extract global results
        $results['globals'] = [];
        foreach ($globalsConfig as $key => $global) {
            $results['globals'][$key] = $results[$key] ?? [];
            unset($results[$key]);
        }
        // Merge page metadata with promise results (for 'page')
        $results['metadata'] = array_merge($metadata, $results['page'] ?? []);
        unset($results['page']);

        // Collate module results
        $results['modulesAsyncData'] = [];
        foreach ($results as $key => $result) {
            if (strpos($key, 'module_') === 0) {
                $index = intval(str_replace('module_', '', $key));
                $results['modulesAsyncData'][$index] = $result;
                unset($results[$key]);
            }
        }
        return $results;
    }

    // Helper: Add translations from a static file
    private function addTranslations(): array
    {
        $locale = $this->language;
        $staticTranslations = json_decode(
            file_get_contents(BASE_PATH . '/application/translations.json'),
            true
        );
        return $this->parseTranslations($staticTranslations, $locale);
    }

    // Existing helper methods remain unchanged:
    private function collectModulePromises(array $modules, string $pageType, array $metadata): array
    {
        $language = $this->language;

        $promises = [];
        $pagePromises = [];
        $basePromises = [];
        $index = 0;

        // load processor specific for the current page (eg. news)
        if (!isset($this->pageProcessor)) {
            $this->loadPageProcessor($pageType);
        }

        // load processor universl for all pages
        if (!isset($this->baseProcessor)) {
            $this->loadPageProcessor('base', true);
        }

        // Gather raw promises from both processors first
        $pagePromises = [];
        if (isset($this->pageProcessor) && method_exists($this->pageProcessor, 'fetchData')) {
            $pagePromises = $this->pageProcessor->fetchData($metadata, []);
        }

        $basePromises = [];
        if (isset($this->baseProcessor) && method_exists($this->baseProcessor, 'fetchData')) {
            $basePromises = $this->baseProcessor->fetchData($metadata, []);
        }

        // Merge the raw arrays and then wrap them with Utils::all
        $allPagePromises = array_merge($pagePromises, $basePromises);
        $promises['page'] = Utils::all($allPagePromises);

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
                // $dataArray = $processor->fetchData($module, $this->apiFetcher, []);
                $dataArray = $processor->fetchData($module, []);
                $promises['module_' . $index] = Utils::all($dataArray);
            }

            $index++;
        }

        return $promises;
    }

    private function flattenPromisesResults(array $promisesResults): array
    {
        $flattened = [];
        foreach ($promisesResults as $key => $result) {
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

    private function processModules(array $pageData): array
    {
        $modules = $pageData['modules'] ?? [];
        $index = 0;
        foreach ($modules as &$module) {
            $type = $module['type'] ?? null;
            if ($type && isset($this->processors[$type])) {
                $moduleAsyncData = $pageData['modulesAsyncData'][$index] ?? [];
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
                $processor = new $className($this->universalModule);
                if ($processor instanceof ModuleProcessorInterface) {
                    $this->processors[$type] = $processor;
                }
            }
        }
    }

    private function loadPageProcessor(string $type, bool $base = false): void
    {
        $processorFile = BASE_PATH . '/application/pages/' . $type . '.php';
        if (file_exists($processorFile)) {
            require_once $processorFile;
            $className = 'App\\Pages\\' . $type;
            if (class_exists($className)) {
                $processor = new $className($this->universalModule);
                if ($processor instanceof PageProcessorInterface) {
                    if ($base) {
                        $this->baseProcessor = $processor;
                    } else {
                        $this->pageProcessor = $processor;
                    }
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

    private function generatePaths(Request $request): array
    {
        $paths = [];
        $uri = $request->getUri();
        $baseUrl = $uri->getScheme() . '://' . $uri->getAuthority();

        // Generate the home URL
        $paths['home'] = (empty($this->context->getLanguage())) ? '/' : '/' . $this->context->getLanguage();

        // Get the current path from the request (without language prefix)
        $currentPath = $request->getUri()->getPath();

        // Generate URLs for the page in other languages
        $supportedLanguages = $this->context->getSupportedLanguages();
        $currentLanguage = $this->context->getLanguage();
        $paths['languages'] = [];
        $paths['alternate_languages'] = [];

        foreach ($supportedLanguages as $language) {
            $langPath = '/' . $language . $currentPath;
            $paths['languages'][$language] = $langPath . '?lang=true';
            $paths['alternate_languages'][$language] = $baseUrl . $langPath;
        }

        // Move the current language URL to the beginning of the array
        if (isset($paths['languages'][$currentLanguage])) {
            $currentLanguageUrl = $paths['languages'][$currentLanguage];
            unset($paths['languages'][$currentLanguage]);
            $paths['languages'] = [$currentLanguage => $currentLanguageUrl] + $paths['languages'];
        }

        return $paths;
    }
}
