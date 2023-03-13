<?php

declare(strict_types=1);

use Oct8pus\NanoRouter\NanoRouter;
use Oct8pus\NanoRouter\NanoRouterException;
use Oct8pus\NanoRouter\Response;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @covers \Oct8pus\NanoRouter\NanoRouter
 */
final class NanoRouterTest extends TestCase
{
    public function setUp() : void
    {
        parent::setUp();
    }

    public function testRoute() : void
    {
        $router = new NanoRouter();

        // 404
        $this->mockRequest('GET', '/');
        $response = $router->resolve();

        $this->assertEquals(404, $response->status());
        $this->assertEquals('Not Found', $response->body());

        // add index route
        $router->addRoute('GET', '/', function () : Response {
            return new Response(200, 'index');
        });

        // add another route
        $router->addRoute('GET', '/hello/', function () : Response {
            return new Response(200, 'hello');
        });

        $this->mockRequest('GET', '/');
        $response = $router->resolve();

        $this->assertEquals(200, $response->status());
        $this->assertEquals('index', $response->body());

        $this->mockRequest('GET', '/hello/');
        $response = $router->resolve();

        $this->assertEquals(200, $response->status());
        $this->assertEquals('hello', $response->body());

        // method not allowed
        $this->mockRequest('POST', '/');
        $response = $router->resolve();

        $this->assertEquals(405, $response->status());
        $this->assertEquals('Method Not Allowed', $response->body());
    }

    public function testRegexRoute() : void
    {
        $router = (new NanoRouter())
            ->addRouteRegex('GET', '~/test(.*).php~', function () use (&$result) {
                return new Response(200, 'test regex');
            });

        $this->mockRequest('GET', '/test.php');
        $response = $router->resolve();

        $this->assertEquals(200, $response->status());
        $this->assertEquals('test regex', $response->body());

        $this->mockRequest('GET', '/test2.php');
        $response = $router->resolve();

        $this->assertEquals(200, $response->status());
        $this->assertEquals('test regex', $response->body());

        $this->mockRequest('GET', '/tes.php');
        $response = $router->resolve();

        $this->assertEquals(new Response(404, 'Not Found'), $response);

        $this->mockRequest('POST', '/test.php');
        $response = $router->resolve();

        $this->assertEquals(new Response(405, 'Method Not Allowed'), $response);
    }

    public function testErrorHandler() : void
    {
        $router = new NanoRouter();

        $router->addErrorHandler(404, function () : Response {
            return new Response(404, 'This page does not exist on the server');
        });

        $this->mockRequest('GET', '/test.php');
        $this->assertEquals(new Response(404, 'This page does not exist on the server'), $router->resolve());
    }

    public function testInvalidRegexRoute() : void
    {
        $router = new NanoRouter();

        $this->expectException(NanoRouterException::class);

        $router->addRouteRegex('GET', '~/test(.*).php', function () {});
    }

    private function mockRequest($method, $uri) : void
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;
    }
}
