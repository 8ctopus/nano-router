<?php

declare(strict_types=1);

use Oct8pus\NanoRouter\NanoRouter;
use Oct8pus\NanoRouter\NanoRouterException;
use PHPUnit\Framework\TestCase;

/**
 * @internal
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

        $result = false;

        $router->addRoute('GET', '/test.php', function () use (&$result) {
            $result = true;
        });

        // success
        $this->mockRequest('GET', '/test.php');
        $router->resolve();
        $this->assertTrue($result);

        $result = false;

        // 404
        $this->mockRequest('GET', '/test2.php');
        $router->resolve();
        $this->assertFalse($result);

        $result = false;

        // method not allowed
        $this->mockRequest('POST', '/test.php');
        $router->resolve();
        $this->assertFalse($result);
    }

    public function testRegexRoute() : void
    {
        $result = false;

        $router = (new NanoRouter())
            ->addRouteRegex('GET', '~/test(.*).php~', function () use (&$result) {
                $result = true;
            });

        $this->mockRequest('GET', '/test.php');
        $router->resolve();
        $this->assertTrue($result);

        $result = false;

        $this->mockRequest('GET', '/test2.php');
        $router->resolve();
        $this->assertTrue($result);

        $result = false;

        $this->mockRequest('GET', '/tes.php');
        $router->resolve();
        $this->assertFalse($result);

        $result = false;

        $this->mockRequest('POST', '/test.php');
        $router->resolve();
        $this->assertFalse($result);
    }

    public function testErrorHandler() : void
    {
        $router = new NanoRouter();

        $result = false;

        $router->addErrorHandler(404, function () use (&$result) {
            $result = true;
        });

        $this->mockRequest('GET', '/test.php');
        $router->resolve();
        $this->assertTrue($result);
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
