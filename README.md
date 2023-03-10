# nano router

[![packagist](http://poser.pugx.org/8ctopus/nano-router/v)](https://packagist.org/packages/8ctopus/nano-router)
[![downloads](http://poser.pugx.org/8ctopus/nano-router/downloads)](https://packagist.org/packages/8ctopus/nano-router)
[![min php version](http://poser.pugx.org/8ctopus/nano-router/require/php)](https://packagist.org/packages/8ctopus/nano-router)
[![license](http://poser.pugx.org/8ctopus/nano-router/license)](https://packagist.org/packages/8ctopus/nano-router)
[![tests](https://github.com/8ctopus/nano-router/actions/workflows/tests.yml/badge.svg)](https://github.com/8ctopus/nano-router/actions/workflows/tests.yml)
![code coverage badge](https://raw.githubusercontent.com/8ctopus/nano-router/image-data/coverage.svg)
![lines of code](https://raw.githubusercontent.com/8ctopus/nano-router/image-data/lines.svg)

An experimental and extremely simple php router

## install

-

```sh
composer require 8ctopus/nano-router
```

- in `index.php`

```php
use Oct8pus\NanoRouter\NanoRouter;

require_once 'vendor/autoload.php';

$router = new NanoRouter();

// add simple route
$router->addRoute('GET', '/test.php', function () {
    echo 'test';
});

// add regex route
$router->addRouteRegex('*', '~/php(.*)/~', function (array $matches) {
    echo 'phpinfo';
});

$router->addErrorHandler(404, function (string $requestPath) {
    http_response_code(404);
    echo "page not found {$requestPath}";
});

// resolve route
$router->resolve();
```

- redirect all traffic (except existing files) to the router in `.htaccess` for those using Apache

```apache
RewriteEngine on

# redirect all not existing files to router
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ /index.php [L]
```

## run tests

```sh
vendor/bin/phpunit --coverage-html coverage
```

## clean code

```sh
vendor/bin/php-cs-fixer fix
```
