<?php

declare(strict_types=1);

namespace Tests;

use Oct8pus\NanoRouter\RouteAlias;
use Oct8pus\NanoRouter\RouteType;

/**
 * @internal
 *
 * @covers \Oct8pus\NanoRouter\RouteAlias
 */
final class RouteAliasTest extends TestCase
{
    public function testAlias() : void
    {
        $route = (new RouteAlias(RouteType::Exact, 'GET', '/test/', static function () : void {}))
            ->setAlias('/new-test/');

        self::assertTrue($route->pathMatches('/test/'));
        self::assertTrue($route->pathMatches('/new-test/'));
        self::assertFalse($route->pathMatches('test/'));
        self::assertFalse($route->pathMatches('/test'));
        self::assertFalse($route->pathMatches('/test/ '));
        self::assertFalse($route->pathMatches('/test2/'));
        self::assertFalse($route->pathMatches('new-test/'));
        self::assertFalse($route->pathMatches('/new-test'));
        self::assertFalse($route->pathMatches('/new-test/ '));
        self::assertFalse($route->pathMatches('/new-test2/'));

        $route = (new RouteAlias(RouteType::StartsWith, 'GET', '/test/', static function () : void {}))
            ->setAlias('/new-test/');

        self::assertTrue($route->pathMatches('/test/'));
        self::assertTrue($route->pathMatches('/test/ '));
        self::assertTrue($route->pathMatches('/test/test2/'));
        self::assertTrue($route->pathMatches('/new-test/'));
        self::assertTrue($route->pathMatches('/new-test/ '));
        self::assertTrue($route->pathMatches('/new-test/test2/'));
        self::assertFalse($route->pathMatches('test/'));
        self::assertFalse($route->pathMatches('/test'));
        self::assertFalse($route->pathMatches('/test2/'));
        self::assertFalse($route->pathMatches('new-test/'));
        self::assertFalse($route->pathMatches('/new-test'));
        self::assertFalse($route->pathMatches('/new-test2/'));

        $route = (new RouteAlias(RouteType::Regex, 'GET', '~/test(.*)\.php~', static function () : void {}))
            ->setAlias('~/new-test(.*)\.php~');

        self::assertTrue($route->pathMatches('/test.php'));
        self::assertTrue($route->pathMatches('/test2.php'));
        self::assertTrue($route->pathMatches('/new-test.php'));
        self::assertTrue($route->pathMatches('/new-test2.php'));
        self::assertFalse($route->pathMatches('test.php'));
        self::assertFalse($route->pathMatches('new-test.php'));
    }

}
