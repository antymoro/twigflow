<?php

namespace App\CmsClients\Sanity\Components;

use App\Context\RequestContext;
use Sanity\BlockContent;

class SanityDataProcessor
{

    private RequestContext $context;
    private array $referenceIds = [];

    public function __construct(RequestContext $context)
    {
        $this->context = $context;
    }

    public function processDataRecursively($data): mixed
    {
        $language = $this->context->getLanguage();

        if (is_array($data)) {
            if (isset($data['_id']) && str_contains($data['_id'], 'drafts.')) {
                return null;
            }

            switch ($data['_type'] ?? null) {
                
                case 'localeString':
                    return $language && isset($data[$language]) ? $data[$language] : '';

                case 'localeText':
                    return $language && isset($data[$language]) ? $data[$language] : '';

                case 'localeBlockContent':
                    $submodules = $this->processHtmlBlockModule($data, $language);
                    foreach ($submodules as &$submodule) {
                        if ($submodule['type'] === 'text') {
                            $submodule['content'] = $this->convertBlocksToHtml($submodule['content']);
                        } elseif ($submodule['type'] === 'image') {
                            $submodule['content']['caption'] = $submodule['content']['caption'][$language] ?? '';
                            $submodule['content']['alt'] = $submodule['content']['alt'][$language] ?? '';
                        }
                    }

                    return [
                        'type' => 'text',
                        'submodules' => $submodules
                    ];

                case 'reference':
                    if (isset($data['_ref']) && !str_contains($data['_ref'], 'image-')) {
                        $this->referenceIds[] = $data['_ref'];
                    }
                    return $data;

                default:
                    $result = [];
                    foreach ($data as $key => $value) {
                        $processedValue = $this->processDataRecursively($value, $language);
                        if ($processedValue !== null) {
                            $result[$key] = $processedValue;
                        }
                    }
                    return $result;
            }
        }
        return $data;
    }

    public function processHtmlBlockModule(array $module): array
    {
        $language = $this->context->getLanguage();

        $submodules = [];
        $currentBlocks = [];

        if ($language) {
            $module = $module[$language] ?? [];
        }

        foreach ($module as $item) {
            if (is_array($item)) {
                switch ($item['_type'] ?? null) {
                    case 'block':
                        $currentBlocks[] = $item;
                        break;
                    case 'imageBlock':
                        if (!empty($currentBlocks)) {
                            $submodules[] = [
                                'type' => 'text',
                                'content' => $currentBlocks
                            ];
                            $currentBlocks = [];
                        }
                        $submodules[] = [
                            'type' => 'image',
                            'content' => $item
                        ];
                        break;
                }
            }
        }

        if (!empty($currentBlocks)) {
            $submodules[] = [
                'type' => 'text',
                'content' => $currentBlocks
            ];
        }

        return $submodules;
    }

    private function convertBlocksToHtml(array $data): string
    {
        $html = '';
        $listStack = [];

        foreach ($data as $item) {
            if (is_array($item)) {
                if (isset($item['_type']) && $item['_type'] === 'block') {
                    if (isset($item['listItem'])) {
                        $listType = $item['listItem'] === 'bullet' ? 'ul' : 'ol';
                        if (empty($listStack) || end($listStack) !== $listType) {
                            if (!empty($listStack)) {
                                $html .= '</' . array_pop($listStack) . '>';
                            }
                            $html .= '<' . $listType . '>';
                            $listStack[] = $listType;
                        }
                        $html .= '<li>' . BlockContent::toHtml($item) . '</li>';
                    } else {
                        while (!empty($listStack)) {
                            $html .= '</' . array_pop($listStack) . '>';
                        }
                        $html .= BlockContent::toHtml($item);
                    }
                } else {
                    $html .= $this->convertBlocksToHtml($item);
                }
            }
        }

        while (!empty($listStack)) {
            $html .= '</' . array_pop($listStack) . '>';
        }

        return $html;
    }

    public function getReferenceIds(): array
    {
        return $this->referenceIds;
    }

    private function findSubmodulesLevel(array $data): ?array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (isset($value[0]['_type']) && in_array($value[0]['_type'], ['block', 'imageBlock'])) {
                    return $data[$key];
                } else {
                    $result = $this->findSubmodulesLevel($value);
                    if ($result !== null) {
                        return $result;
                    }
                }
            }
        }
        return null;
    }
}
