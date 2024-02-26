<?php

declare(strict_types=1);

namespace Demo;

use Exception;
// use any PSR-7 implementation
use HttpSoft\Emitter\SapiEmitter;
use HttpSoft\Message\Response;
use HttpSoft\Message\ServerRequestFactory;
use HttpSoft\Message\Stream;
use HttpSoft\ServerRequest\ServerRequestCreator;
use Oct8pus\NanoRouter\MiddlewareType;
use Oct8pus\NanoRouter\NanoRouter;
use Oct8pus\NanoRouter\Route;
use Oct8pus\NanoRouter\RouteAlias;
use Oct8pus\NanoRouter\RouteException;
use Oct8pus\NanoRouter\RouteType;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;

require_once __DIR__ . '/../../vendor/autoload.php';

(new Run())
    ->pushHandler(new PrettyPageHandler())
    ->register();

$router = new NanoRouter(Response::class, ServerRequestFactory::class);

$route = new Route(RouteType::Exact, 'GET', '/', static function () : ResponseInterface {
    $stream = new Stream();

    $stream->write(<<<'BODY'
    <html>
    <head>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@1/css/pico.min.css" crossorigin="anonymous" integrity="0269018275915a04492010a90829b0b9cfe66ce59358a7a99055e29a8d6742a9">
    </head>
    <body class="container">
    <h1>Hello World!</h1>
    <p>You're on the index page. Here's a list of links: </p>
    <ul>
    <li>link to the <a href="/test/">test page</a></li>
    <li>link to <a href="/phpinfo/">one</a> of the php* pages</li>
    <li>This is a <a href="/not-found/">broken link</a> for testing purposes</li>
    <li><a href="/admin/test/">route requires http auth (111/111)</a></li>
    <li><a href="/route-exception/">route exception test</a></li>
    <li><a href="/fatal-exception-handled/">fatal exception test (handled exception = a response is returned)</a></li>
    <li><a href="/fatal-exception-unhandled/">fatal exception test (unhandled exception)</a></li>
    </ul>
    </body>
    </html>
    BODY);

    return new Response(200, [], $stream);
});

$router->addRoute($route);

$alias = new RouteAlias(RouteType::Exact, ['HEAD', 'GET'], '/test/', static function (ServerRequestInterface $request) : ResponseInterface {
    $stream = new Stream();

    $stream->write(<<<BODY
    <html>
    <head>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@1/css/pico.min.css" crossorigin="anonymous" integrity="0269018275915a04492010a90829b0b9cfe66ce59358a7a99055e29a8d6742a9">
    </head>
    <body class="container">
    <h1>You're on page {$request->getRequestTarget()}</h1>
    <p>Here's a link to <a href="/">return to the index</a>!</p>
    </body>
    </html>
    BODY);

    return new Response(200, [], $stream);
});

$alias->setAlias('/test-alias/');

$router->addRoute($alias);

$router->addRoute(new Route(RouteType::StartsWith, '*', '/php', static function (ServerRequestInterface $request) : ResponseInterface {
    $stream = new Stream();
    $stream->write('match starts with route' . PHP_EOL);
    $stream->write('request target - ' . $request->getRequestTarget());

    return new Response(200, ['content-type' => 'text/plain'], $stream);
}));

$router->addRoute(new Route(RouteType::Exact, 'GET', '/admin/test/', static function () : ResponseInterface {
    $stream = new Stream();
    $stream->write('You\'re logged in');

    return new Response(200, ['content-type' => 'text/plain'], $stream);
}));

$router->addRoute(new Route(RouteType::Exact, 'GET', '/route-exception/', static function () : ResponseInterface {
    throw new RouteException('not authorized', 403);
}));

$router->addRoute(new Route(RouteType::Exact, 'GET', '/fatal-exception-handled/', static function () : ResponseInterface {
    throw new Exception('fatal error', 500);
}));

$router->addRoute(new Route(RouteType::Exact, 'GET', '/fatal-exception-unhandled/', static function () : ResponseInterface {
    throw new Exception('fatal error');
}));

$router->addErrorHandler(404, static function (ServerRequestInterface $request) : ResponseInterface {
    $stream = new Stream();
    $stream->write(<<<BODY
    <html>
    <head>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@1/css/pico.min.css" crossorigin="anonymous" integrity="0269018275915a04492010a90829b0b9cfe66ce59358a7a99055e29a8d6742a9">
    </head>
    <body class="container">
    <h1>Sorry we lost that page</h1>
    <h2>{$request->getRequestTarget()}</h2>
    </body>
    </html>
    BODY);

    return new Response(404, [], $stream);
});

$router->addErrorHandler(405, static function () : ResponseInterface {
    return new Response(405);
});

$router->addMiddleware('*', '~(.*)~', MiddlewareType::Post, static function (ResponseInterface $response) : ResponseInterface {
    return $response->withHeader('X-Powered-By', '8ctopus');
});

$router->addMiddleware('*', '~(.*)~', MiddlewareType::Pre, static function (ServerRequestInterface $request) : ?ResponseInterface {
    error_log('middleware intercepted - ' . $request->getRequestTarget());
    return null;
});

$router->addMiddleware('*', '~/admin/~', MiddlewareType::Pre, static function (ServerRequestInterface $request) : ?ResponseInterface {
    $server = $request->getServerParams();

    $login = $server['PHP_AUTH_USER'] ?? null;
    $password = $server['PHP_AUTH_PW'] ?? null;

    if (!($login === '111' && $password === '111')) {
        return new Response(401, ['WWW-Authenticate' => 'Basic']);
    }

    return null;
});

$request = ServerRequestCreator::createFromGlobals($_SERVER, $_FILES, $_COOKIE, $_GET, $_POST);

$response = $router->resolve($request);

(new SapiEmitter())
    ->emit($response);
