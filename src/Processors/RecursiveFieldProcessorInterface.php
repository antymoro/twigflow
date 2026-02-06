<?php

namespace App\Processors;

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
