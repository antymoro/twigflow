<?php

namespace App\Processors\RecursiveProcessors;

interface RecursiveFieldProcessorInterface
{
    /**
     * Check if the given value should be processed by this processor.
     */
    public function supports($value): bool;

    /**
     * Process the given value.
     */
    public function process($value);
}