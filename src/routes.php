<?php

namespace App;

use Slim\App;

return function (App $app) {
    // Route to clear cache
    $app->get('/clear-cache', \App\Controllers\CacheController::class . ':clearCache');

    // Route to handle dynamic pages using the PageController with slug
    $app->get('/{slug}', \App\Controllers\PageController::class . ':show');
};