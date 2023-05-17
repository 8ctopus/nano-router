# nano router

[![packagist](http://poser.pugx.org/8ctopus/nano-router/v)](https://packagist.org/packages/8ctopus/nano-router)
[![downloads](http://poser.pugx.org/8ctopus/nano-router/downloads)](https://packagist.org/packages/8ctopus/nano-router)
[![min php version](http://poser.pugx.org/8ctopus/nano-router/require/php)](https://packagist.org/packages/8ctopus/nano-router)
[![license](http://poser.pugx.org/8ctopus/nano-router/license)](https://packagist.org/packages/8ctopus/nano-router)
[![tests](https://github.com/8ctopus/nano-router/actions/workflows/tests.yml/badge.svg)](https://github.com/8ctopus/nano-router/actions/workflows/tests.yml)
![code coverage badge](https://raw.githubusercontent.com/8ctopus/nano-router/image-data/coverage.svg)
![lines of code](https://raw.githubusercontent.com/8ctopus/nano-router/image-data/lines.svg)

An experimental and extremely simple PSR-7 router

## demo

To view the demo, run `php -S localhost:80 demo/public/index.php -t demo/public/` and open your browser at `http://localhost`.

The demo can also be started using Docker `docker-compose up &`.

## install

- `composer require 8ctopus/nano-router`

- redirect all traffic (except existing files) to the router in `.htaccess` for those using Apache

```apache
RewriteEngine on

# redirect all not existing files to router
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ /index.php [L]
```

and for nginx (untested)

```nginx
location / {
    try_files $uri $uri/ /index.php$is_args$args;
}
```

- create `index.php`

```php
// use any PSR-7 implementation
use HttpSoft\Message\Response;
use HttpSoft\Emitter\SapiEmitter;
use Oct8pus\NanoRouter\NanoRouter;
use Psr\Http\Message\ResponseInterface;

require_once 'vendor/autoload.php';

$router = new NanoRouter(Response::class);

$router
    // add simple route
    ->addRoute('GET', '/test.php', function () : ResponseInterface {
        return new Response(200, 'test');
    })
    // add regex route
    ->addRouteRegex('*', '~/php(.*)/~', function (array $matches) : ResponseInterface {
        return new Response(200, 'phpinfo');
    })
    ->addErrorHandler(404, function (string $requestPath) : ResponseInterface {
        return new Response(404, "page not found {$requestPath}");
    });

// resolve request
$response = $router->resolve();

// send response to client
(new SapiEmitter())
    ->emit($response);
```

## run tests

    composer test

## clean code

    composer fix(-risky)
