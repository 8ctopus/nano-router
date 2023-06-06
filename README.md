# nano router

[![packagist](http://poser.pugx.org/8ctopus/nano-router/v)](https://packagist.org/packages/8ctopus/nano-router)
[![downloads](http://poser.pugx.org/8ctopus/nano-router/downloads)](https://packagist.org/packages/8ctopus/nano-router)
[![min php version](http://poser.pugx.org/8ctopus/nano-router/require/php)](https://packagist.org/packages/8ctopus/nano-router)
[![license](http://poser.pugx.org/8ctopus/nano-router/license)](https://packagist.org/packages/8ctopus/nano-router)
[![tests](https://github.com/8ctopus/nano-router/actions/workflows/tests.yml/badge.svg)](https://github.com/8ctopus/nano-router/actions/workflows/tests.yml)
![code coverage badge](https://raw.githubusercontent.com/8ctopus/nano-router/image-data/coverage.svg)
![lines of code](https://raw.githubusercontent.com/8ctopus/nano-router/image-data/lines.svg)

An experimental PSR-7, PSR-17 router

## introduction for beginners

The purpose of a router is to match a user request to a request handler, the later will return a response that will be sent back to the user.

[PSR-7](https://www.php-fig.org/psr/psr-7/) defines the request and response interfaces, while [PSR-17](https://www.php-fig.org/psr/psr-17/) defines the factories for creating them. In other words, factories are used to create the request and response objects from the user request and the request handler response.

Here's some pseudocode that explains the concept:

```php
$router = new Router();

$router->addRoute('GET', '/test.php', function (ServerRequestInterface $request) : ResponseInterface {
    return new Response(200, ['content-type' => 'text/plain'], 'You\'ve reached page /test.php');
})

// create user request from globals
$request = ServerRequestCreator::createFromGlobals($_SERVER, $_FILES, $_COOKIE, $_GET, $_POST);

// router finds a handler for the request
$response = $router->resolve($request);

// send response to client (echoes internally)
(new SapiEmitter())
    ->emit($response);
```

## demo

To view the demo, run `php -S localhost:80 demo/public/index.php -t demo/public/` and open your browser at `http://localhost`.

The demo can also be started using Docker `docker-compose up &`.

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
    ->addRoute('GET', '/test.php', function (ServerRequestInterface $request) : ResponseInterface {
        $stream = new Stream();
        $stream->write('test.php');

        return new Response(200, [], $stream);
    })
    // add starts with route
    ->addRouteStartsWith('GET', '/test/', function (ServerRequestInterface $request) : ResponseInterface {
        $stream = new Stream();
        $stream->write('request target - '. $request->getRequestTarget());

        return new Response(200, [], $stream);
    })
    // add regex route
    ->addRouteRegex('*', '~/php(.*)/~', function (ServerRequestInterface $request) : ResponseInterface {
        $stream = new Stream();
        $stream->write('request target - '. $request->getRequestTarget());

        return new Response(200, [], $stream);
    })
    ->addErrorHandler(404, function (ServerRequestInterface $request) : ResponseInterface {
        $stream = new Stream();
        $stream->write('page not found - ' . $request->getRequestTarget());

        return new Response(404, [], $stream);
    })
    ->addMiddleware('*', '~(.*)~', 'post', function (ResponseInterface $response, ServerRequestInterface $request) : ResponseInterface {
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

## advanced functionalities

There is more to it, it's just not in the readme yet, such as:

- multiple route methods
- pre and post middleware
- route exception and generic exception handling

but most of it can be experimented with in the demo

## run tests

    composer test

## clean code

    composer fix(-risky)
