<?php

declare(strict_types=1);

namespace Demo;

use Oct8pus\NanoRouter\NanoRouter;
use Oct8pus\NanoRouter\Response;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;

require_once '../../vendor/autoload.php';

(new Run())
    ->pushHandler(new PrettyPageHandler())
    ->register();

$router = new NanoRouter();

$router->addRoute('GET', '/', function () : Response {
    $body = <<<BODY
    <html>
    <body>
    <h1>Hello World!</h1>
    <p>You're on the index page.Here's a link to the <a href="/test/">test page</a>.</p>
    </body>
    </html>
    BODY;

    return new Response(200, $body);
});

$router->addRoute('GET', '/test/', function () : Response {
    $body = <<<BODY
    <html>
    <body>
    <h1>You're on test page</h1>
    <p>Here's a link to <a href="/">return to the index</a>!</p>
    </body>
    </html>
    BODY;

    return new Response(200, $body);
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
