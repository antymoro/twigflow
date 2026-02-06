<?php

namespace App\Pages;

interface PageProcessorInterface
{
    public function process(array $module, array $data): array;
}
