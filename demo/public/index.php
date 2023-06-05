<?php

declare(strict_types=1);

namespace Demo;

use Exception;
// use any PSR-7 implementation
use HttpSoft\Emitter\SapiEmitter;
use HttpSoft\Message\Response;
use HttpSoft\Message\Stream;
use Oct8pus\NanoRouter\NanoRouter;
use Oct8pus\NanoRouter\RouteException;
use Psr\Http\Message\ResponseInterface;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;

require_once __DIR__ . '/../../vendor/autoload.php';

(new Run())
    ->pushHandler(new PrettyPageHandler())
    ->register();

$router = new NanoRouter(Response::class);

$router->addRoute('GET', '/', function () : ResponseInterface {
    $stream = new Stream();
    $stream->write(<<<'BODY'
    <html>
    <body>
    <h1>Hello World!</h1>
    <p>You're on the index page. Here's a list of links: </p>
    <ul>
    <li>link to the <a href="/test/">test page</a></li>
    <li>link to <a href="/phpinfo/">one</a> of the php* pages</li>
    <li>This is a <a href="/not-found/">broken link</a> for testing purposes</li>
    <li><a href="/route-exception/">route exception test</a></li>
    <li><a href="/fatal-exception-handled/">fatal exception test (handled exception = a response is returned)</a></li>
    <li><a href="/fatal-exception-unhandled/">fatal exception test (unhandled exception)</a></li>
    </ul>
    </body>
    </html>
    BODY);

    return new Response(200, [], $stream);
});

$router->addRoute(['HEAD', 'GET'], '/test/', function () : ResponseInterface {
    $stream = new Stream();
    $stream->write(<<<'BODY'
    <html>
    <body>
    <h1>You're on test page</h1>
    <p>Here's a link to <a href="/">return to the index</a>!</p>
    </body>
    </html>
    BODY);

    return new Response(200, [], $stream);
});

$router->addRouteStartWith('*', '/php', function () : ResponseInterface {
    $stream = new Stream();
    $stream->write('match starts with route');

    return new Response(200, [], $stream);
});

$router->addRoute('GET', '/route-exception/', function () : ResponseInterface {
    throw new RouteException('not authorized', 403);
});

$router->addRoute('GET', '/fatal-exception-handled/', function () : ResponseInterface {
    throw new Exception('fatal error', 500);
});

$router->addRoute('GET', '/fatal-exception-unhandled/', function () : ResponseInterface {
    throw new Exception('fatal error');
});

$router->addErrorHandler(404, function () : ResponseInterface {
    $stream = new Stream();
    $stream->write(<<<'BODY'
    <html>
    <body>
    <h1>Sorry we lost that page</h1>
    </body>
    </html>
    BODY);

    return new Response(404, [], $stream);
});

$router->addErrorHandler(405, function () : ResponseInterface {
    return new Response(405);
});

$router->addMiddleware('*', '~(.*)~', 'post', function (ResponseInterface $response) : ResponseInterface {
    return $response->withHeader('X-Powered-By', '8ctopus');
});

$router->addMiddleware('*', '~(.*)~', 'pre', function () : ?ResponseInterface {
    error_log('middleware intercepted - ' . $_SERVER['REQUEST_URI']);
    return null;
});

$router->addMiddleware('*', '~/api/~', 'pre', function () : ?ResponseInterface {
    if (!isset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'])) {
        return new Response(401, ['WWW-Authenticate' => 'Basic']);
    }

    return null;
});

$response = $router->resolve();

(new SapiEmitter())
    ->emit($response);
