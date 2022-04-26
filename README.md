# nano router

An experimental and extremely simple php router

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
use oct8pus\NanoRouter\NanoRouter;

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

## clean code

```sh
vendor/bin/php-cs-fixer fix
```
