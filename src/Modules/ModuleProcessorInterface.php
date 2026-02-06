<?php

namespace App\Modules;

interface ModuleProcessorInterface
{
    public function process(array $module, array $data): array;
}
