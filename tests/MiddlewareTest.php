<?php

declare(strict_types=1);

namespace Tests;

use HttpSoft\Message\Response;
use Oct8pus\NanoRouter\Middleware;
use Oct8pus\NanoRouter\MiddlewareType;
use Oct8pus\NanoRouter\NanoRouterException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @internal
 *
 * @covers \Oct8pus\NanoRouter\Middleware
 */
final class MiddlewareTest extends TestCase
{
    public function testPathMatches() : void
    {
        $middleware = new Middleware(MiddlewareType::Pre, 'GET', '~/test(.*)\.php~', static function () : void {});

        self::assertTrue($middleware->pathMatches('/test.php'));
        self::assertTrue($middleware->pathMatches('/test2.php'));
        self::assertFalse($middleware->pathMatches('test.php'));
    }

    public function testMethodMatches() : void
    {
        $middleware = new Middleware(MiddlewareType::Pre, 'GET', '~/test/~', static function () : void {});

        self::assertTrue($middleware->methodMatches('GET'));
        self::assertFalse($middleware->methodMatches('POST'));
        self::assertFalse($middleware->methodMatches('PUT'));
        self::assertFalse($middleware->methodMatches('DELETE'));
        self::assertFalse($middleware->methodMatches('OPTIONS'));

        $middleware = new Middleware(MiddlewareType::Pre, ['GET', 'POST'], '~/test/~', static function () : void {});

        self::assertTrue($middleware->methodMatches('GET'));
        self::assertTrue($middleware->methodMatches('POST'));
        self::assertFalse($middleware->methodMatches('PUT'));
        self::assertFalse($middleware->methodMatches('DELETE'));
        self::assertFalse($middleware->methodMatches('OPTIONS'));

        $middleware = new Middleware(MiddlewareType::Pre, '*', '~/test/~', static function () : void {});

        self::assertTrue($middleware->methodMatches('GET'));
        self::assertTrue($middleware->methodMatches('POST'));
        self::assertTrue($middleware->methodMatches('PUT'));
        self::assertTrue($middleware->methodMatches('DELETE'));
        self::assertTrue($middleware->methodMatches('OPTIONS'));
    }

    public function testMatches() : void
    {
        $middleware = new Middleware(MiddlewareType::Pre, 'GET', '~/test/~', static function () : void {});

        self::assertTrue($middleware->matches('GET', '/test/'));
        self::assertFalse($middleware->matches('POST', '/test/'));
        self::assertFalse($middleware->matches('GET', '/test2/'));
    }

    public function testCall() : void
    {
        $middleware = new Middleware(MiddlewareType::Pre, 'GET', '~/test/~', static function (ServerRequestInterface $request) : ?ResponseInterface {
            $request = $request;
            return new Response(200);
        });

        $request = $this->mockRequest('GET', '/test/');

        self::assertEquals(new Response(200), $middleware->callPre($request));

        $middleware = new Middleware(MiddlewareType::Post, 'GET', '~/test/~', static function (ResponseInterface $response, ServerRequestInterface $request) : ?ResponseInterface {
            switch ($request->getUri()->getPath()) {
                case '/test/':
                    return $response;

                default:
                    return new Response(404);
            }
            return $response;
        });

        self::assertEquals(new Response(405), $middleware->callPost(new Response(405), $request));

        $request = $this->mockRequest('GET', '/test/2');
        self::assertEquals(new Response(404), $middleware->callPost(new Response(405), $request));
    }

    public function testInvalidRegex() : void
    {
        self::expectException(NanoRouterException::class);
        self::expectExceptionMessage('invalid regex - ~/test(.*)\.php');

        new Middleware(MiddlewareType::Post, 'GET', '~/test(.*)\.php', static function () : void {});
    }
}
