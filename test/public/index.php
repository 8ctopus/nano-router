<?php

use oct8pus\NanoRouter\NanoRouter;
use Whoops\Run;
use Whoops\Handler\PrettyPageHandler;

require_once '../../vendor/autoload.php';

$whoops = new Run();
$whoops->pushHandler(new PrettyPageHandler());
$whoops->register();

$router = new NanoRouter();

$router->addRoute('*', '/test.php', function () {
    echo 'test';
});

$router->addRouteRegex('*', '#^/php(.*)/#', function (?array $matches) {
    echo 'phpinfo '. $matches[1];
});

$router->addRoute('*', '404', function () {
    http_response_code(404);

    echo '404 route';
});

// resolve route
$router->resolve();
