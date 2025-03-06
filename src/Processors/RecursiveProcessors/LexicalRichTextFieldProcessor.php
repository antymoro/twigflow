<?php

namespace App\Processors\RecursiveProcessors;

use App\Parsers\LexicalRichTextParser;

class LexicalRichTextFieldProcessor implements RecursiveFieldProcessorInterface
{
    private LexicalRichTextParser $parser;

    public function __construct(LexicalRichTextParser $parser)
    {
        $this->parser = $parser;
    }

    public function supports($value): bool
    {
        if (is_string($value)) {
            return false;
        }

        return is_array($value) && isset($value['root']);
    }

    public function process($value)
    {
        return $this->parser->parse($value);
    }
}