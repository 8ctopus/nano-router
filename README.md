# nano router

[![packagist](https://poser.pugx.org/8ctopus/nano-router/v)](https://packagist.org/packages/8ctopus/nano-router)
[![downloads](https://poser.pugx.org/8ctopus/nano-router/downloads)](https://packagist.org/packages/8ctopus/nano-router)
[![min php version](https://poser.pugx.org/8ctopus/nano-router/require/php)](https://packagist.org/packages/8ctopus/nano-router)
[![license](https://poser.pugx.org/8ctopus/nano-router/license)](https://packagist.org/packages/8ctopus/nano-router)
[![tests](https://github.com/8ctopus/nano-router/actions/workflows/tests.yml/badge.svg)](https://github.com/8ctopus/nano-router/actions/workflows/tests.yml)
![code coverage badge](https://raw.githubusercontent.com/8ctopus/nano-router/image-data/coverage.svg)
![lines of code](https://raw.githubusercontent.com/8ctopus/nano-router/image-data/lines.svg)

An experimental PSR-7, PSR-17 router

## features

- very fast (less than 2ms on simple routing)
- uses PSR-7 and PSR-17 standards
- no dependencies besides PSR-7/17

While I consider it still experimental, I have been using it in production to host [legend.octopuslabs.io](https://legend.octopuslabs.io/) without any issues so far.

## introduction for beginners

The purpose of a router is to match a user (client) http request to a specific function that will handle the user request and deliver a response to the client.

[PSR-7](https://www.php-fig.org/psr/psr-7/) defines the request and response interfaces, while [PSR-17](https://www.php-fig.org/psr/psr-17/) defines the factories for creating them. In other words, factories are used to create the request and response objects.

Here's some pseudo-code that explains the concept:

```php
$router = new Router();

// add route
$router->addRoute(new Route(RouteType::Exact, 'GET', '/test.php', function (ServerRequestInterface $request) : ResponseInterface {
    return new Response(200, ['Content-Type' => 'text/plain'], 'You\'ve reached the test page');
}));

// create user http request
$request = ServerRequestCreator::createFromGlobals($_SERVER, $_FILES, $_COOKIE, $_GET, $_POST);

// resolve finds the function that handles the user request, calls it and returns its response
$response = $router->resolve($request);

// send response to client (echoes internally)
(new SapiEmitter())
    ->emit($response);
```

## demo

To play with the demo, clone the repo, run `php -S localhost:80 demo/public/index.php -t demo/public/` and open your browser at `http://localhost`.
Alternatively you can run the demo within a Docker container `docker-compose up &`.

## install

- `composer require 8ctopus/nano-router`

- if you don't have any preference for the PSR-7 implementation, install [HttpSoft](https://github.com/httpsoft) `composer require httpsoft/http-message httpsoft/http-emitter`

- redirect all traffic (except existing files) to the router in `.htaccess` for those using Apache

```apache
RewriteEngine on

# redirect all not existing files and directories to router
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [END]
```

and for nginx (untested)

```nginx
location / {
    try_files $uri $uri/ /index.php$is_args$args;
}
```

- create `index.php`

```php
use Oct8pus\NanoRouter\NanoRouter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// use any PSR-7, PSR-17 implementations, here HttpSoft
use HttpSoft\Emitter\SapiEmitter;
use HttpSoft\Message\Response;
use HttpSoft\Message\ServerRequestFactory;
use HttpSoft\Message\Stream;
use HttpSoft\ServerRequest\ServerRequestCreator;

require_once __DIR__ . '/vendor/autoload.php';

$router = new NanoRouter(Response::class, ServerRequestFactory::class);

$router
    // add simple route
    ->addRoute(new Route(RouteType::Exact, 'GET', '/test.php', function (ServerRequestInterface $request) : ResponseInterface {
        $stream = new Stream();
        $stream->write('test.php');

        return new Response(200, [], $stream);
    }))
    // add starts with route
    ->addRoute(new Route(RouteType::StartsWith, ['GET', 'POST'], '/test/', function (ServerRequestInterface $request) : ResponseInterface {
        $stream = new Stream();
        $stream->write('request target - '. $request->getRequestTarget());

        return new Response(200, [], $stream);
    }))
    // add regex route
    ->addRoute(new Route(RouteType::Regex, '*', '~/php(.*)/~', function (ServerRequestInterface $request) : ResponseInterface {
        $stream = new Stream();
        $stream->write('request target - '. $request->getRequestTarget());

        return new Response(200, [], $stream);
    }))
    ->addErrorHandler(404, function (ServerRequestInterface $request) : ResponseInterface {
        $stream = new Stream();
        $stream->write('page not found - ' . $request->getRequestTarget());

        return new Response(404, [], $stream);
    })
    ->addMiddleware('*', '~(.*)~', MiddlewareType::Post, function (ResponseInterface $response, ServerRequestInterface $request) : ResponseInterface {
        return $response->withHeader('X-Powered-By', '8ctopus');
    });

// create request from globals
$request = ServerRequestCreator::createFromGlobals($_SERVER, $_FILES, $_COOKIE, $_GET, $_POST);

// resolve request into a response
$response = $router->resolve($request);

// send response to client
(new SapiEmitter())
    ->emit($response);
```

## exception handling

By default, `Throwable`, and everything inherited from it such as `Exception`, are caught by the router provided the exception occurs within the route code. That functionality can be disabled in the constructor by setting it to `false`.

```php
new NanoRouter(Response::class, ServerRequestFactory::class, false, false);
```

The `RouteException` class offers an elegant way to deal with http errors such as `404`, `401`, `429`, .... The router will automatically catch the exception and return the appropriate response to the client.

```php
throw new RouteException('page not found', 404);

// client sees 404 page
```

## advanced functionalities

There is more to it, it's just not in the readme yet, most of it can be experimented within the demo, such as:

- pre and post middleware

## run tests

    composer test

## clean code

    composer fix(-risky)

## todo ideas

- add basePath
- class wrapper for subroutes
- should pre middleware only work on valid requests? now not valid routes are still going through the middleware probably we need both
- add starts with middleware
- check psr-15 middleware
- how to easily route inside class?
