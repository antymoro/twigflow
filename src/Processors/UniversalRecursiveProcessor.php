<?php

namespace App\Processors;

use App\Processors\RecursiveFieldProcessorInterface;

class UniversalRecursiveProcessor
{
    /**
     * @var RecursiveFieldProcessorInterface[]
     */
    private array $processors;

    /**
     * @param RecursiveFieldProcessorInterface[] $processors
     */
    public function __construct(array $processors)
    {
        $this->processors = $processors;
    }

    /**
     * Recursively traverse data and apply any processor that supports a given value.
     *
     * @param mixed $data
     * @return mixed
     */
    public function parseRecursive($data)
    {
        // First, check if any processor supports the whole block (string or array).
        foreach ($this->processors as $processor) {
            if ($processor->supports($data)) {
                // Process the data and then recursively process its result.
                $data = $processor->process($data);
                // Break since one processor handled this level.
                break;
            }
        }

        // Now, if the data is an array, process each element recursively.
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->parseRecursive($value);
            }
        }

        return $data;
    }
}