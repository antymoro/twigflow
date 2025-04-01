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

                    $blockModule = [
                        'type' => 'text',
                        'submodules' => $submodules
                    ];

                    $blockModule = $this->processDataRecursively($blockModule, $language);

                    return $blockModule;

                case 'localeSimpleBlockContent':
                    return $this->convertBlocksToHtml($data);

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
                        
                    case 'youtube':
                    case 'video':
                        if (!empty($currentBlocks)) {
                            $submodules[] = [
                                'type' => 'text',
                                'content' => $currentBlocks
                            ];
                            $currentBlocks = [];
                        }
                        $submodules[] = [
                            'type' => 'video',
                            'content' => $item
                        ];
                        break;
                        
                    case 'quoteBlock':
                        if (!empty($currentBlocks)) {
                            $submodules[] = [
                                'type' => 'text',
                                'content' => $currentBlocks
                            ];
                            $currentBlocks = [];
                        }
                        $submodules[] = [
                            'type' => 'quote',
                            'content' => $item
                        ];
                        break;
                        
                    case 'accordion':
                        if (!empty($currentBlocks)) {
                            $submodules[] = [
                                'type' => 'text',
                                'content' => $currentBlocks
                            ];
                            $currentBlocks = [];
                        }
                        $submodules[] = [
                            'type' => 'accordion',
                            'content' => $item
                        ];
                        break;
                        
                    case 'text_inner':
                        if (!empty($currentBlocks)) {
                            $submodules[] = [
                                'type' => 'text',
                                'content' => $currentBlocks
                            ];
                            $currentBlocks = [];
                        }
                        
                        // process the nested text content
                        if (isset($item['text']) && isset($item['text']['_type']) && $item['text']['_type'] === 'localeBlockContent') {
                            $nestedSubmodules = $this->processHtmlBlockModule($item['text']);
                            $submodules = array_merge($submodules, $nestedSubmodules);
                        }
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
        $listStack = []; // Stack to track open lists (each element is the tag to close)
        
        // Define custom serializers with proper handling for links
        $serializers = [
            'marks' => [
                'link' => function ($mark, $children) {
                    $href = $mark['href'] ?? '#';
                    $targetAttr = '';
                    
                    // Check if blank property exists and is true
                    if (isset($mark['blank']) && $mark['blank'] === true) {
                        $targetAttr = ' target="_blank" rel="noopener noreferrer"';
                    }
                    
                    return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '"' . $targetAttr . '>' . 
                        (is_array($children) && isset($children[0]) ? $children[0] : (is_array($children) ? implode('', $children) : $children)) . 
                    '</a>';
                }
            ]
        ];
        
        foreach ($data as $item) {
            if (!is_array($item)) {
                continue;
            }
            
            // Check if this is a list item (block with a "listItem" value)
            if (isset($item['_type']) && $item['_type'] === 'block' && isset($item['listItem'])) {
                // Determine the desired nested level (default to 1)
                $newLevel = isset($item['level']) ? (int) $item['level'] : 1;
                // Determine list tag based on listItem type
                $listTag = $item['listItem'] === 'bullet' ? 'ul' : 'ol';
    
                // If new level is deeper than current, open new nested lists
                while (count($listStack) < $newLevel) {
                    $html .= "<{$listTag}>";
                    $listStack[] = $listTag;
                }
                // If new level is shallower, close extra open lists
                while (count($listStack) > $newLevel) {
                    $tag = array_pop($listStack);
                    $html .= "</{$tag}>";
                }
                // (Optional) If at the same level but list type changes, close the current list and open a new one.
                if (!empty($listStack)) {
                    $currentTag = end($listStack);
                    if ($currentTag !== $listTag) {
                        // Close current list and open new one
                        $html .= "</" . array_pop($listStack) . ">";
                        $html .= "<{$listTag}>";
                        $listStack[] = $listTag;
                    }
                }
                
                // Render the list item with custom serializers
                $content = BlockContent::toHtml($item, ['serializers' => $serializers]);
                $html .= "<li>{$content}</li>";
            } else {
                // This item is not part of a list. Close any open lists.
                while (!empty($listStack)) {
                    $tag = array_pop($listStack);
                    $html .= "</{$tag}>";
                }
                // Render the item normally with custom serializers
                $html .= BlockContent::toHtml($item, ['serializers' => $serializers]);
            }
        }
        
        // Close any remaining open lists.
        while (!empty($listStack)) {
            $tag = array_pop($listStack);
            $html .= "</{$tag}>";
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
