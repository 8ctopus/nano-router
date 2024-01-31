<?php

declare(strict_types=1);

namespace Tests;

use Oct8pus\NanoRouter\NanoRouter;

class NanoRouterMock extends NanoRouter
{
    protected static function errorLog(string $message) : void
    {
        echo $message;
    }
}
