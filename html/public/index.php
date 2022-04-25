<?php

require_once 'NanoRouter.php';

$router = new NanoRouter();

function test()
{
    echo __FUNCTION__;
}

function phpinfo2()
{
    echo __FUNCTION__;
}

$router->addRoute('/test.php', 'test');

$router->addRouteRegex('/php.*/', 'phpinfo2');

// resolve route
$router->resolve();
