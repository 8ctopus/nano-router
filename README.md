# nano router

An experiment at creating a simple router.

# install

- install

```sh
composer require 8ctopus/nano-router
```

- redirect all traffic (except existing files) to the router in `.htaccess`

```htaccess
RewriteEngine on

# redirect all not existing files to router
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ /index.php
```

- And finally `index.php`

```php
<?php

use oct8pus\NanoRouter;

require_once '../../vendor/autoload.php';

$router = new NanoRouter();

$router->addRoute('/test.php', 'test');

$router->addRouteRegex('/php.*/', 'phpinfo2');

// resolve route
$router->resolve();


function test()
{
    echo __FUNCTION__;
}

function phpinfo2()
{
    echo __FUNCTION__;
}
```
