<?php

declare(strict_types=1);

namespace Tests;

use Oct8pus\NanoRouter\NanoRouterException;
use Oct8pus\NanoRouter\Route;
use Oct8pus\NanoRouter\RouteType;

/**
 * @internal
 *
 * @covers \Oct8pus\NanoRouter\Route
 */
final class RouteTest extends TestCase
{
    public function testPathMatches() : void
    {
        $route = new Route(RouteType::Exact, 'GET', '/test/', static function () : void {});

        self::assertTrue($route->pathMatches('/test/'));
        self::assertFalse($route->pathMatches('test/'));
        self::assertFalse($route->pathMatches('/test'));
        self::assertFalse($route->pathMatches('/test/ '));
        self::assertFalse($route->pathMatches('/test2/'));

        $route = new Route(RouteType::StartsWith, 'GET', '/test/', static function () : void {});

        self::assertTrue($route->pathMatches('/test/'));
        self::assertFalse($route->pathMatches('test/'));
        self::assertFalse($route->pathMatches('/test'));
        self::assertTrue($route->pathMatches('/test/ '));
        self::assertTrue($route->pathMatches('/test/test2/'));
        self::assertFalse($route->pathMatches('/test2/'));

        $route = new Route(RouteType::Regex, 'GET', '~/test(.*)\.php~', static function () : void {});

        self::assertTrue($route->pathMatches('/test.php'));
        self::assertTrue($route->pathMatches('/test2.php'));
        self::assertFalse($route->pathMatches('test.php'));
    }

    public function testMethodMatches() : void
    {
        $route = new Route(RouteType::Exact, 'GET', '/test/', static function () : void {});

        self::assertTrue($route->methodMatches('GET'));
        self::assertFalse($route->methodMatches('POST'));
        self::assertFalse($route->methodMatches('PUT'));
        self::assertFalse($route->methodMatches('DELETE'));
        self::assertFalse($route->methodMatches('OPTIONS'));

        $route = new Route(RouteType::Exact, '*', '/test/', static function () : void {});

        self::assertTrue($route->methodMatches('GET'));
        self::assertTrue($route->methodMatches('POST'));
        self::assertTrue($route->methodMatches('PUT'));
        self::assertTrue($route->methodMatches('DELETE'));
        self::assertTrue($route->methodMatches('OPTIONS'));
    }

    public function testMatches() : void
    {
        $route = new Route(RouteType::Exact, 'GET', '/test/', static function () : void {});

        self::assertTrue($route->matches('GET', '/test/'));
        self::assertFalse($route->matches('POST', '/test/'));
        self::assertFalse($route->matches('GET', '/test2/'));
    }

    public function testInvalidRegex() : void
    {
        self::expectException(NanoRouterException::class);
        self::expectExceptionMessage('invalid regex');

        new Route(RouteType::Regex, 'GET', '~/test(.*)\.php', static function () : void {});
    }
}
