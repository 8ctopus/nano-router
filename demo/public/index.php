<?php

declare(strict_types=1);

use Oct8pus\NanoRouter\NanoRouter;
use Oct8pus\NanoRouter\Response;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;

require_once '../../vendor/autoload.php';

$whoops = new Run();
$whoops->pushHandler(new PrettyPageHandler());
$whoops->register();

$router = new NanoRouter();

$router->addRoute('GET', '/test.php', function () : Response {
    return new Response(200, 'test');
});

$router->addRouteRegex('*', '~^/php(.*)/~', function (?array $matches) : Response {
    return new Response(200, 'phpinfo ' . $matches[1]);
});

$router->addErrorHandler(404, function () : Response {
    return new Response(404, 'page not found');
});

$router->addErrorHandler(405, function () : Response {
    return new Response(405, 'method not allowed');
});

// resolve route
$router->resolve();
