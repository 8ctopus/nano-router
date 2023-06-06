<?php

declare(strict_types=1);

namespace Tests;

use HttpSoft\ServerRequest\ServerRequestCreator;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Psr\Http\Message\ServerRequestInterface;

abstract class TestCase extends BaseTestCase
{
    protected function mockRequest(string $method, string $uri, string $query = '') : ServerRequestInterface
    {
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $_SERVER['REQUEST_SCHEME'] = 'http';
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['QUERY_STRING'] = $query;

        return ServerRequestCreator::createFromGlobals($_SERVER, $_FILES, $_COOKIE, $_GET, $_POST);
    }
}
