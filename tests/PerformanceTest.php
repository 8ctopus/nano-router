<?php

declare(strict_types=1);

namespace Tests;

use HttpSoft\Message\Response;
use Oct8pus\NanoRouter\NanoRouter;
use Oct8pus\NanoTimer\NanoTimer;
use PHPUnit\Framework\TestCase;
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
        $router = new NanoRouter(Response::class);

        $methods = [
            'HEAD',
            'GET',
            'POST',
            'DELETE',
            'PUT',
            'PATCH'
        ];

        $count = count($methods);

        $timer = (new NanoTimer())
            ->logMemoryPeakUse();

        // add random no-regex routes
        for ($i = 0; $i < 500; ++$i) {
            $url = '/' . bin2hex(random_bytes(2));

            $router->addRoute($methods[rand(0, $count -1)], $url, function () : ResponseInterface {
                return new Response(200);
            });
        }

        $timer->measure('add 500 random non-regex routes');

        // add random regex routes
        for ($i = 0; $i < 500; ++$i) {
            $url = '~^/' . bin2hex(random_bytes(2)) . '.*~';

            $router->addRouteRegex($methods[rand(0, $count -1)], $url, function () : ResponseInterface {
                return new Response(200);
            });
        }

        $timer->measure('add 500 random regex routes');

        $found = 0;

        for ($i = 0; $i < 3000; ++$i) {
            $this->mockRequest($methods[rand(0, $count -1)], '/' . bin2hex(random_bytes(2)));
            $response = $router->resolve();

            if ($response->getStatusCode() === 200) {
                ++$found;
            }
        }

        $timer->measure('resolve 3000 random routes');

        echo PHP_EOL . $timer->table();
        echo PHP_EOL . "found {$found} routes";

        self::assertTrue(true);
    }

    private function mockRequest($method, $uri) : void
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;
    }
}
