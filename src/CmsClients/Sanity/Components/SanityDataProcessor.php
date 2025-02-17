<?php

namespace App\CmsClients\Sanity\Components;

use Sanity\BlockContent;

class SanityDataProcessor
{
    private array $referenceIds = [];

    public function processDataRecursively($data, ?string $language): mixed
    {
        if (is_array($data)) {
            if (isset($data['_id']) && str_contains($data['_id'], 'drafts.')) {
                return null;
            }

            switch ($data['_type'] ?? null) {
                case 'localeString':
                    return $language && isset($data[$language]) ? $data[$language] : $data;
                case 'localeText':
                    return $language && isset($data[$language]) ? $data[$language] : $data;
                case 'localeBlockContent':
                    return $this->processHtmlBlockModule($data);
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

    public function processReferencesRecursively($references, $language) {
        return [];
    }

    public function processHtmlBlockModule(array $module): array
    {
        $html = $this->convertBlocksToHtml($module);
        return ['text' => $html, 'type' => 'text', '_type' => 'text'];
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
}