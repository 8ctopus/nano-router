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
