<?php

namespace App\Modules;

use App\Modules\Manager\ModuleProcessorInterface;

class m_hero implements ModuleProcessorInterface
{
    public function process(array $module): array
    {
        $module['title'] = strtoupper('this is Homepage! (not Sparta)');
        return $module;
    }
}
