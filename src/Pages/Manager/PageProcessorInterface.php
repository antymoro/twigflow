<?php

namespace App\Pages\Manager;

interface PageProcessorInterface
{
    public function process(array $module, array $data): array;
}
