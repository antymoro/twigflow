<?php
// filepath: /Users/daniel/code/huncwot/frameworks/twigflow/src/Parsers/LexicalRichTextParser.php

namespace App\Parsers;

class LexicalRichTextParser
{
    public function parse(array $data): string
    {
        if (isset($data['root'])) {
            $root = $data['root'];
            if (isset($root['type']) && $root['type'] === 'root') {
                $nodes = $root['children'] ?? [];
            } else {
                $nodes = $root;
            }
        } elseif (isset($data['type']) && $data['type'] === 'root') {
            $nodes = $data['children'] ?? [];
        } else {
            $nodes = $data;
        }

        return $this->renderNodes($nodes);
    }

    protected function renderNodes(array $nodes): string
    {
        $html = '';
        foreach ($nodes as $node) {
            $type = $node['type'] ?? '';
            switch ($type) {
                case 'paragraph':
                    $content = $this->renderNodes($node['children'] ?? []);
                    $html .= '<p>' . $content . '</p>';
                    break;
                case 'heading':
                    $level = isset($node['level']) ? (int)$node['level'] : 1;
                    $content = $this->renderNodes($node['children'] ?? []);
                    $html .= '<h' . $level . '>' . $content . '</h' . $level . '>';
                    break;
                case 'text':
                    $text = htmlspecialchars($node['text'] ?? '', ENT_QUOTES, 'UTF-8');
                    // Apply formatting if available.
                    if (!empty($node['bold'])) {
                        $text = '<strong>' . $text . '</strong>';
                    }
                    if (!empty($node['italic'])) {
                        $text = '<em>' . $text . '</em>';
                    }
                    $html .= $text;
                    break;
                case 'list':
                    $tag = !empty($node['ordered']) ? 'ol' : 'ul';
                    $content = $this->renderNodes($node['children'] ?? []);
                    $html .= '<' . $tag . '>' . $content . '</' . $tag . '>';
                    break;
                case 'listItem':
                    $content = $this->renderNodes($node['children'] ?? []);
                    $html .= '<li>' . $content . '</li>';
                    break;
                default:
                    // For any unknown type, try to process its children.
                    if (isset($node['children'])) {
                        $html .= $this->renderNodes($node['children']);
                    }
                    break;
            }
        }
        return $html;
    }
}