# nano router

An experimental and extremely simple php router

[![Latest Stable Version](http://poser.pugx.org/8ctopus/nano-router/v)](https://packagist.org/packages/8ctopus/nano-router) [![Total Downloads](http://poser.pugx.org/8ctopus/nano-router/downloads)](https://packagist.org/packages/8ctopus/nano-router)  [![License](http://poser.pugx.org/8ctopus/nano-router/license)](https://packagist.org/packages/8ctopus/nano-router) [![PHP Version Require](http://poser.pugx.org/8ctopus/nano-router/require/php)](https://packagist.org/packages/8ctopus/nano-router)

## install

-

```sh
composer require 8ctopus/nano-router
```

- redirect all traffic (except existing files) to the router in `.htaccess`

```apache
RewriteEngine on

# redirect all not existing files to router
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ /index.php [L]
```

- And finally `index.php`

```php
use Oct8pus\NanoRouter\NanoRouter;

require_once 'vendor/autoload.php';

$router = new NanoRouter();

// add simple route
$router->addRoute('GET', '/test.php', function () {
    echo 'test';
});

// add regex route - do not use / as regex delimiter
$router->addRouteRegex('*', '#/php(.*)/#', function (array $matches) {
    echo 'phpinfo';
});

// resolve route
$router->resolve();
```

## run tests

```sh
./vendor/bin/phpunit .
./vendor/bin/phpunit . --coverage-text
```

## clean code

```sh
vendor/bin/php-cs-fixer fix
```
