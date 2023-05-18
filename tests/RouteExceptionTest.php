<?php

declare(strict_types=1);

namespace Tests;

use HttpSoft\Message\Response;
use HttpSoft\Message\Stream;
use Oct8pus\NanoRouter\NanoRouter;
use Oct8pus\NanoRouter\NanoRouterException;
use Oct8pus\NanoRouter\RouteException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

/**
 * @internal
 *
 * @covers \Oct8pus\NanoRouter\RouteException
 */
final class RouteExceptionTest extends TestCase
{
    public function testOK() : void
    {
        $exception = new RouteException('hello', 403);

        static::assertSame('hello', $exception->getMessage());
        static::assertSame(403, $exception->getCode());
        static::assertFalse($exception->debug());

        $exception = new RouteException('hello', 403, true);

        static::assertSame('hello', $exception->getMessage());
        static::assertSame(403, $exception->getCode());
        static::assertTrue($exception->debug());
    }

    public function testInvalidStatusCode() : void
    {
        static::expectException(NanoRouterException::class);
        static::expectExceptionMessage('invalid status code - 99');

        new RouteException('test', 99);
    }

    public function testInvalidStatusCode2() : void
    {
        static::expectException(NanoRouterException::class);
        static::expectExceptionMessage('invalid status code - 600');

        new RouteException('test', 600);
    }
}
