<?php

namespace App;

use Slim\App;

return function (App $app) {

    $app->get('/{slug}', \App\Controllers\PageController::class . ':show');

    $app->post('/clear-cache', \App\Controllers\CacheController::class . ':clearCache');

};