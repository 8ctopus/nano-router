<?php

declare(strict_types=1);

namespace Tests;

use Oct8pus\NanoRouter\NanoRouterException;
use Oct8pus\NanoRouter\RouteException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(RouteException::class)]
final class RouteExceptionTest extends TestCase
{
    public function testOK() : void
    {
        $exception = new RouteException('hello', 403);

        self::assertSame('hello', $exception->getMessage());
        self::assertSame(403, $exception->getCode());

        $exception = new RouteException('hello', 403);

        self::assertSame('hello', $exception->getMessage());
        self::assertSame(403, $exception->getCode());
    }

    public function testInvalidStatusCode() : void
    {
        self::expectException(NanoRouterException::class);
        self::expectExceptionMessage('invalid status code - 99');

        new RouteException('test', 99);
    }

    public function testInvalidStatusCode2() : void
    {
        self::expectException(NanoRouterException::class);
        self::expectExceptionMessage('invalid status code - 600');

        new RouteException('test', 600);
    }
}
