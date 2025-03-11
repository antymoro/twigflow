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
        $previousWasLinebreak = false;

        foreach ($nodes as $node) {
            $type = $node['type'] ?? '';
            switch ($type) {
                case 'paragraph':
                    $content = $this->renderNodes($node['children'] ?? []);
                    $html .= '<p>' . $content . '</p>';
                    $previousWasLinebreak = false;
                    break;
                case 'heading':
                    $level = isset($node['level']) ? (int)$node['level'] : 1;
                    $content = $this->renderNodes($node['children'] ?? []);
                    $html .= '<h' . $level . '>' . $content . '</h' . $level . '>';
                    $previousWasLinebreak = false;
                    break;
                case 'text':
                    $text = htmlspecialchars($node['text'] ?? '', ENT_QUOTES, 'UTF-8');
                    $format = $node['format'] ?? 0;

                    // apply formatting if available.
                    if ($format & 1) {
                        $text = '<strong>' . $text . '</strong>';
                    }
                    if ($format & 2) {
                        $text = '<em>' . $text . '</em>';
                    }
                    if ($format & 4) {
                        $text = '<u>' . $text . '</u>';
                    }
                    if ($format & 8) {
                        $text = '<s>' . $text . '</s>';
                    }

                    $html .= $text;
                    $previousWasLinebreak = false;
                    break;
                case 'list':
                    $tag = !empty($node['ordered']) ? 'ol' : 'ul';
                    $content = $this->renderNodes($node['children'] ?? []);
                    $html .= '<' . $tag . '>' . $content . '</' . $tag . '>';
                    $previousWasLinebreak = false;
                    break;
                case 'listItem':
                    $content = $this->renderNodes($node['children'] ?? []);
                    $html .= '<li>' . $content . '</li>';
                    $previousWasLinebreak = false;
                    break;
                case 'quote':
                    $content = $this->renderNodes($node['children'] ?? []);
                    $html .= '<blockquote>' . $content . '</blockquote>';
                    $previousWasLinebreak = false;
                    break;
                case 'linebreak':
                    if (!$previousWasLinebreak) {
                        $html .= '</p><p>';
                        $previousWasLinebreak = true;
                    }
                    break;
                default:
                    // for any unknown type, try to process its children.
                    if (isset($node['children'])) {
                        $html .= $this->renderNodes($node['children']);
                    }
                    $previousWasLinebreak = false;
                    break;
            }
        }

        // ensure the HTML ends with a closing paragraph tag if it started with one
        if (substr($html, -3) === '<p>') {
            $html = substr($html, 0, -3);
        } elseif (substr($html, -4) === '</p>') {
            $html .= '</p>';
        }

        return $html;
    }
}