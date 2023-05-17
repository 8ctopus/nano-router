<?php

declare(strict_types=1);

namespace Tests;

use HttpSoft\Message\Response;
use HttpSoft\Message\Stream;
use Oct8pus\NanoRouter\NanoRouter;
use Oct8pus\NanoRouter\NanoRouterException;
//use Oct8pus\NanoRouter\Response;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @covers \Oct8pus\NanoRouter\NanoRouter
 */
final class NanoRouterTest extends TestCase
{
    protected function setUp() : void
    {
        parent::setUp();
    }

    public function testRoute() : void
    {
        $router = new NanoRouter(Response::class);

        // 404
        $this->mockRequest('GET', '/');
        $response = $router->resolve();

        static::assertSame(404, $response->getStatusCode());
        static::assertSame('', (string) $response->getBody());
        static::assertSame('Not Found', $response->getReasonPhrase());

        // add index route
        $router->addRoute('GET', '/', function () : Response {
            $stream = new Stream();
            $stream->write('index');
            return new Response(200, [], $stream);
        });

        // add another route
        $router->addRoute('GET', '/hello/', function () : Response {
            $stream = new Stream();
            $stream->write('hello');
            return new Response(200, [], $stream);
        });

        $this->mockRequest('GET', '/');
        $response = $router->resolve();

        static::assertSame(200, $response->getStatusCode());
        static::assertSame('index', (string) $response->getBody());

        $this->mockRequest('GET', '/hello/');
        $response = $router->resolve();

        static::assertSame(200, $response->getStatusCode());
        static::assertSame('hello', (string) $response->getBody());

        // method not allowed
        $this->mockRequest('POST', '/');
        $response = $router->resolve();

        static::assertSame(405, $response->getStatusCode());
        static::assertSame('', (string) $response->getBody());
        static::assertSame('Method Not Allowed', $response->getReasonPhrase());
    }

    public function testRegexRoute() : void
    {
        $router = (new NanoRouter(Response::class))
            ->addRouteRegex('GET', '~/test(.*).php~', function () {
                $stream = new Stream();
                $stream->write('test regex');
                return new Response(200, [], $stream);
            });

        $this->mockRequest('GET', '/test.php');
        $response = $router->resolve();

        static::assertSame(200, $response->getStatusCode());
        static::assertSame('test regex', (string) $response->getBody());

        $this->mockRequest('GET', '/test2.php');
        $response = $router->resolve();

        static::assertSame(200, $response->getStatusCode());
        static::assertSame('test regex', (string) $response->getBody());

        $this->mockRequest('GET', '/tes.php');
        $response = $router->resolve();

        static::assertEquals(new Response(404), $response);

        $this->mockRequest('POST', '/test.php');
        $response = $router->resolve();

        static::assertEquals(new Response(405), $response);
    }

    public function testErrorHandler() : void
    {
        $router = new NanoRouter(Response::class);

        $router->addErrorHandler(404, function () : Response {
            $stream = new Stream();
            $stream->write('This page does not exist on the server');
            return new Response(404, [], $stream);
        });

        $this->mockRequest('GET', '/test.php');

        $response = $router->resolve();

        static::assertEquals(404, $response->getStatusCode());
        static::assertEquals('Not Found', $response->getReasonPhrase());
        static::assertEquals('This page does not exist on the server', (string) $response->getBody());
    }

    public function testInvalidRegexRoute() : void
    {
        $router = new NanoRouter(Response::class);

        static::expectException(NanoRouterException::class);

        $router->addRouteRegex('GET', '~/test(.*).php', function () : void {});
    }

    private function mockRequest($method, $uri) : void
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;
    }
}
