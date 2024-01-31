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
