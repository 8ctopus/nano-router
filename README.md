# nano router

An experiment at creating a simple router.

# install

- install

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
use oct8pus\NanoRouter;

require_once '../../vendor/autoload.php';

$router = new NanoRouter();

$router->addRoute('/test.php', function () {
    echo 'test';
});

$router->addRouteRegex('/php.*/', function () {
    echo 'phpinfo';
});

// resolve route
$router->resolve();
```
