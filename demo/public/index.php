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

$router->addRoute('GET', '/', function () : Response {
    $body = <<<BODY
    <html>
    <body>
    <h1>Hello World!</h1>

    Here's a link to the <a href="/test/">test page</a>!
    </body>
    </html>
    BODY;

    return new Response(200, $body);
});

$router->addRoute('GET', '/test/', function () : Response {
    return new Response(200, 'This is the test page');
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
$response = $router->resolve();

$response->send();
