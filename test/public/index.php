<?php

use oct8pus\NanoRouter;

require_once '../../vendor/autoload.php';

$router = new NanoRouter();

$router->addRoute('/test.php', function () {
    echo 'test';
});

$router->addRouteRegex('/php.*/', function () {
    echo 'phpinfo';
});

// resolve route
$router->resolve();
