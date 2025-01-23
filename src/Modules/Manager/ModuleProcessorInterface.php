<?php

namespace App\Modules\Manager;

interface ModuleProcessorInterface {
    public function process(array $module): array;
}