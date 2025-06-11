<?php

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
                    $tag = isset($node['tag']) ? $node['tag'] : 'h1';
                    $content = $this->renderNodes($node['children'] ?? []);
                    $html .= '<' . $tag . '>' . $content . '</' . $tag . '>';
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
                    // if ($format & 8) {
                    //     $text = '<s>' . $text . '</s>';
                    // }

                    $html .= $text;
                    $previousWasLinebreak = false;
                    break;
                case 'list':
                    $tag = $node['listType'] === 'number' ? 'ol' : 'ul';
                    $content = $this->renderNodes($node['children'] ?? []);
                    $html .= '<' . $tag . '>' . $content . '</' . $tag . '>';
                    $previousWasLinebreak = false;
                    break;
                case 'listitem':
                    $content = $this->renderNodes($node['children'] ?? []);
                    $html .= '<li>' . $content . '</li>';
                    $previousWasLinebreak = false;
                    break;
                case 'quote':
                    $content = $this->renderNodes($node['children'] ?? []);
                    $html .= '<blockquote><p>' . $content . '</p></blockquote>';
                    $previousWasLinebreak = false;
                    break;
                case 'linebreak':
                    if (!$previousWasLinebreak) {
                        $html .= '</p><p>';
                        $previousWasLinebreak = true;
                    }
                    break;
                case 'link':
                    $fields = $node['fields'] ?? [];
                    $url = '#'; // Default URL

                    if (isset($fields['linkType']) && $fields['linkType'] === 'internal') {
                        if (isset($fields['doc']['value']['slug'])) {
                            $url = '/' . ltrim($fields['doc']['value']['slug'], '/');
                        }
                    } elseif (isset($fields['url'])) {
                        $url = $fields['url'];
                    }

                    $newTab = (str_starts_with($url, 'http')) ? ' target="_blank"' : ((!empty($fields['newTab']) && $fields['newTab']) ? ' target="_blank"' : '');
                    $content = $this->renderNodes($node['children'] ?? []);
                    $html .= '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"' . $newTab . '>' . $content . '</a>';
                    $previousWasLinebreak = false;
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

        // // ensure the HTML ends with a closing paragraph tag if it started with one
        // if (!preg_match('#</p>\s*$#', $html)) {
        //     $html .= '</p>';
        // }

        return $html;
    }
}
