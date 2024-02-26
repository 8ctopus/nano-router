<?php

declare(strict_types=1);

namespace Tests;

use HttpSoft\Message\Response;
use HttpSoft\Message\ServerRequestFactory;
use Oct8pus\NanoRouter\NanoRouter;
use Oct8pus\NanoRouter\Route;
use Oct8pus\NanoRouter\RouteType;
use Oct8pus\NanoTimer\NanoTimer;
use Psr\Http\Message\ResponseInterface;

/**
 * @internal
 *
 * @coversNothing
 */
final class PerformanceTest extends TestCase
{
    public function testRoute() : void
    {
        $router = new NanoRouter(Response::class, ServerRequestFactory::class);

        $methods = [
            'HEAD',
            'GET',
            'POST',
            'DELETE',
            'PUT',
            'PATCH',
        ];

        $count = count($methods);

        $timer = (new NanoTimer())
            ->logMemoryPeakUse();

        // add random no-regex routes
        for ($i = 0; $i < 500; ++$i) {
            $url = '/' . bin2hex(random_bytes(2));

            $router->addRoute(new Route(RouteType::Exact, $methods[rand(0, $count - 1)], $url, static function () : ResponseInterface {
                return new Response(200);
            }));
        }

        $timer->measure('add 500 random exact routes');

        // add random regex routes
        for ($i = 0; $i < 500; ++$i) {
            $url = '/' . bin2hex(random_bytes(2));

            $router->addRoute(new Route(RouteType::StartsWith, $methods[rand(0, $count - 1)], $url, static function () : ResponseInterface {
                return new Response(200);
            }));
        }

        $timer->measure('add 500 random starts with routes');

        // add random regex routes
        for ($i = 0; $i < 500; ++$i) {
            $url = '~^/' . bin2hex(random_bytes(2)) . '.*~';

            $router->addRoute(new Route(RouteType::Regex, $methods[rand(0, $count - 1)], $url, static function () : ResponseInterface {
                return new Response(200);
            }));
        }

        $timer->measure('add 500 random regex routes');

        $found = 0;

        for ($i = 0; $i < 3000; ++$i) {
            $request = $this->mockRequest($methods[rand(0, $count - 1)], '/' . bin2hex(random_bytes(2)));
            $response = $router->resolve($request);

            if ($response->getStatusCode() === 200) {
                ++$found;
            }
        }

        $timer->measure('resolve 3000 random routes');

        echo PHP_EOL . $timer->table();
        echo PHP_EOL . "found {$found} routes";

        self::assertTrue(true);
    }
}
