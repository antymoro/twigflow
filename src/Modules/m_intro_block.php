<?php

namespace App\Modules;

use App\Modules\Manager\ModuleProcessorInterface;

class m_intro_block implements ModuleProcessorInterface {
    public function process(array $module): array {
        $module['title'] = strtoupper('strona testowa!');
        return $module;
    }
}