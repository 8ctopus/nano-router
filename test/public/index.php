<?php

use oct8pus\NanoRouter\NanoRouter;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;

require_once '../../vendor/autoload.php';

$whoops = new Run();
$whoops->pushHandler(new PrettyPageHandler());
$whoops->register();

$router = new NanoRouter();

$router->addRoute('*', '/test.php', function () {
    echo 'test';
});

$router->addRouteRegex('*', '#^/php(.*)/#', function (?array $matches) {
    echo 'phpinfo ' . $matches[1];
});

$router->addErrorHandler(404, function () {
    http_response_code(404);

    echo 'Page not found';
});

$router->addErrorHandler(405, function () {
    http_response_code(405);

    echo 'Method not allowed';
});

// resolve route
$router->resolve();
