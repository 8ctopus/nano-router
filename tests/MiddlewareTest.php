<?php

declare(strict_types=1);

namespace Tests;

use Oct8pus\NanoRouter\Middleware;
use Oct8pus\NanoRouter\NanoRouterException;

/**
 * @internal
 *
 * @covers \Oct8pus\NanoRouter\Middleware
 */
final class MiddlewareTest extends TestCase
{
    public function testPathMatches() : void
    {
        $middleware = new Middleware('pre', 'GET', '~/test(.*)\.php~', static function () : void {});

        self::assertTrue($middleware->pathMatches('/test.php'));
        self::assertTrue($middleware->pathMatches('/test2.php'));
        self::assertFalse($middleware->pathMatches('test.php'));
    }

    public function testMethodMatches() : void
    {
        $middleware = new Middleware('pre', 'GET', '~/test/~', static function () : void {});

        self::assertTrue($middleware->methodMatches('GET'));
        self::assertFalse($middleware->methodMatches('POST'));
        self::assertFalse($middleware->methodMatches('PUT'));
        self::assertFalse($middleware->methodMatches('DELETE'));
        self::assertFalse($middleware->methodMatches('OPTIONS'));

        $middleware = new Middleware('pre', ['GET', 'POST'], '~/test/~', static function () : void {});

        self::assertTrue($middleware->methodMatches('GET'));
        self::assertTrue($middleware->methodMatches('POST'));
        self::assertFalse($middleware->methodMatches('PUT'));
        self::assertFalse($middleware->methodMatches('DELETE'));
        self::assertFalse($middleware->methodMatches('OPTIONS'));

        $middleware = new Middleware('pre', '*', '~/test/~', static function () : void {});

        self::assertTrue($middleware->methodMatches('GET'));
        self::assertTrue($middleware->methodMatches('POST'));
        self::assertTrue($middleware->methodMatches('PUT'));
        self::assertTrue($middleware->methodMatches('DELETE'));
        self::assertTrue($middleware->methodMatches('OPTIONS'));
    }

    public function testMatches() : void
    {
        $middleware = new Middleware('pre', 'GET', '~/test/~', static function () : void {});

        self::assertTrue($middleware->matches('GET', '/test/'));
        self::assertFalse($middleware->matches('POST', '/test/'));
        self::assertFalse($middleware->matches('GET', '/test2/'));
    }

    public function testMiddlewareInvalidRegex() : void
    {
        self::expectException(NanoRouterException::class);
        self::expectExceptionMessage('invalid regex - ~/test(.*)\.php');

        new Middleware('post', 'GET', '~/test(.*)\.php', static function () : void {});
    }

    public function testMiddlewareInvalidWhen() : void
    {
        self::expectException(NanoRouterException::class);
        self::expectExceptionMessage('invalid when clause');

        new Middleware('after', 'GET', '~/test(.*)\.php~', static function () : void {});
    }
}
