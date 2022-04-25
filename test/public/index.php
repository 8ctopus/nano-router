<?php

use oct8pus\NanoRouter;

require_once '../../vendor/autoload.php';

$router = new NanoRouter();

$router->addRoute('/test.php', function () {
    echo 'test';
});

$router->addRouteRegex('#/php(.*)/#', function (?array $matches) {
    echo 'phpinfo '. $matches[1];
});

$router->addRoute('404', function () {
    http_response_code(404);

    echo '404 route';
});

// resolve route
$router->resolve();
