<?php

namespace App\CmsClients\Sanity;

use App\Context\RequestContext;
use Sanity\BlockContent;

class SanityDataProcessor
{

    private const BLOCK_TYPE_MAP = [
        'imageBlock' => 'image',
        'youtube' => 'video',
        'video' => 'video',
        'quoteBlock' => 'quote',
        'accordion' => 'accordion',
        'linkButton' => 'linkButton',
    ];

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
                    return $this->processBlockContent($data, true);

                case 'blockContent':
                    return $this->processBlockContent($data, false);

                case 'localeSimpleBlockContent':
                case 'simpleBlockContent':
                    return $this->convertBlocksToHtml($data);

                case 'reference':
                    if (isset($data['_ref']) && !str_contains($data['_ref'], 'image-')) {
                        $this->referenceIds[] = $data['_ref'];
                    }
                    return $data;

                default:
                    $result = [];
                    foreach ($data as $key => $value) {
                        $processedValue = $this->processDataRecursively($value);
                        if ($processedValue !== null) {
                            $result[$key] = $processedValue;
                        }
                    }
                    return $result;
            }
        }
        return $data;
    }

    /**
     * Shared logic for localeBlockContent and blockContent cases.
     * Calls processHtmlBlockModule, localizes image fields, converts text to HTML,
     * then recurses to resolve any remaining nested types.
     */
    private function processBlockContent(array $data, bool $isLocalized): array
    {
        $inputData = $isLocalized ? $data : ($data['title'] ?? []);
        $submodules = $this->processHtmlBlockModule($inputData, $isLocalized);

        foreach ($submodules as &$submodule) {
            if ($submodule['type'] === 'text') {
                $submodule['content'] = $this->convertBlocksToHtml($submodule['content']);
            } elseif ($submodule['type'] === 'image') {
                $this->localizeImageFields($submodule['content'], $isLocalized);
            }
        }

        return $this->processDataRecursively([
            'type' => 'text',
            'submodules' => $submodules,
        ]);
    }

    private function localizeImageFields(array &$content, bool $isLocalized): void
    {
        $language = $this->context->getLanguage();

        if ($isLocalized) {
            $content['caption'] = $content['caption'][$language] ?? '';
            $content['alt'] = $content['alt'][$language] ?? '';
        } else {
            $content['caption'] = $content['caption'][$language] ?? ($content['caption'] ?? '');
            $content['alt'] = $content['alt'][$language] ?? ($content['alt'] ?? '');
        }
    }

    public function processHtmlBlockModule(array $module, bool $isLocalized = true): array
    {
        $language = $this->context->getLanguage();

        $submodules = [];
        $currentBlocks = [];

        if ($isLocalized && $language) {
            $module = $module[$language] ?? [];
        }

        foreach ($module as $item) {
            if (!is_array($item)) {
                continue;
            }

            $type = $item['_type'] ?? null;

            if ($type === 'block') {
                $currentBlocks[] = $item;
            } elseif (isset(self::BLOCK_TYPE_MAP[$type])) {
                $this->flushCurrentBlocks($currentBlocks, $submodules);
                $submodules[] = [
                    'type' => self::BLOCK_TYPE_MAP[$type],
                    'content' => $item,
                ];
            } elseif ($type === 'text_inner') {
                $this->flushCurrentBlocks($currentBlocks, $submodules);
                $this->processTextInnerBlock($item, $submodules);
            } elseif ($type === 'list') {
                $this->flushCurrentBlocks($currentBlocks, $submodules);
                unset($item['_type'], $item['_key']);
                $submodules[] = [
                    'type' => 'list',
                    'content' => $item,
                ];
            }
        }

        $this->flushCurrentBlocks($currentBlocks, $submodules);

        return $submodules;
    }

    private function flushCurrentBlocks(array &$currentBlocks, array &$submodules): void
    {
        if (!empty($currentBlocks)) {
            $submodules[] = [
                'type' => 'text',
                'content' => $currentBlocks,
            ];
            $currentBlocks = [];
        }
    }

    private function processTextInnerBlock(array $item, array &$submodules): void
    {
        if (!isset($item['text']['_type'])) {
            return;
        }

        if ($item['text']['_type'] === 'localeBlockContent') {
            $nestedSubmodules = $this->processHtmlBlockModule($item['text']);
            $submodules = array_merge($submodules, $nestedSubmodules);
        } elseif ($item['text']['_type'] === 'blockContent') {
            $nestedSubmodules = $this->processHtmlBlockModule($item['text']['title'] ?? [], false);
            $submodules = array_merge($submodules, $nestedSubmodules);
        }
    }

    private function convertBlocksToHtml(array $data): string
    {
        $html = '';
        $listStack = [];
        $serializers = $this->getBlockContentSerializers();

        foreach ($data as $item) {
            if (!is_array($item)) {
                continue;
            }

            if (isset($item['_type']) && $item['_type'] === 'list') {
                $html .= $this->closeOpenLists($listStack);
                $html .= $this->renderCustomList($item);
                continue;
            }

            if (isset($item['_type']) && $item['_type'] === 'block' && isset($item['listItem'])) {
                $newLevel = isset($item['level']) ? (int) $item['level'] : 1;
                $listTag = $item['listItem'] === 'bullet' ? 'ul' : 'ol';

                while (count($listStack) < $newLevel) {
                    $html .= "<{$listTag}>";
                    $listStack[] = $listTag;
                }
                while (count($listStack) > $newLevel) {
                    $tag = array_pop($listStack);
                    $html .= "</{$tag}>";
                }
                if (!empty($listStack)) {
                    $currentTag = end($listStack);
                    if ($currentTag !== $listTag) {
                        $html .= "</" . array_pop($listStack) . ">";
                        $html .= "<{$listTag}>";
                        $listStack[] = $listTag;
                    }
                }

                $content = BlockContent::toHtml($item, ['serializers' => $serializers]);
                $html .= "<li>{$content}</li>";
            } else {
                $html .= $this->closeOpenLists($listStack);
                $html .= BlockContent::toHtml($item, ['serializers' => $serializers]);
            }
        }

        $html .= $this->closeOpenLists($listStack);

        return $html;
    }

    private function getBlockContentSerializers(): array
    {
        return [
            'marks' => [
                'link' => function ($mark, $children) {
                    $href = $mark['href'] ?? '#';
                    $targetAttr = '';

                    if (isset($mark['blank']) && $mark['blank'] === true) {
                        $targetAttr = ' target="_blank" rel="noopener noreferrer"';
                    }

                    return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '"' . $targetAttr . '>' .
                        (is_array($children) && isset($children[0]) ? $children[0] : (is_array($children) ? implode('', $children) : $children)) .
                        '</a>';
                }
            ]
        ];
    }

    private function closeOpenLists(array &$listStack): string
    {
        $html = '';
        while (!empty($listStack)) {
            $tag = array_pop($listStack);
            $html .= "</{$tag}>";
        }
        return $html;
    }

    private function renderCustomList(array $item): string
    {
        $title = $item['title'] ?? '';
        $html = "<h3>" . htmlspecialchars($title) . "</h3>";
        $html .= "<ul>";
        foreach (($item['items'] ?? []) as $listItem) {
            $html .= "<li>" . htmlspecialchars($listItem) . "</li>";
        }
        $html .= "</ul>";
        return $html;
    }

    public function getReferenceIds(): array
    {
        return $this->referenceIds;
    }
}
