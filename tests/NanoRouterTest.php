<?php

declare(strict_types=1);

namespace Tests;

use Exception;
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
 * @covers \Oct8pus\NanoRouter\NanoRouter
 */
final class NanoRouterTest extends TestCase
{
    protected function setUp() : void
    {
        parent::setUp();
    }

    public function testRoute() : void
    {
        $router = new NanoRouterMock(Response::class);

        // 404
        $this->mockRequest('GET', '/');
        $response = $router->resolve();

        static::assertSame(404, $response->getStatusCode());
        static::assertEmpty((string) $response->getBody());
        static::assertSame('Not Found', $response->getReasonPhrase());

        // add index route
        $router->addRoute(['HEAD', 'GET'], '/', function () : ResponseInterface {
            if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
                return new Response(200);
            }

            $stream = new Stream();
            $stream->write('index');
            return new Response(200, [], $stream);
        });

        // add another route
        $router->addRoute('*', '/hello/', function () : ResponseInterface {
            $stream = new Stream();
            $stream->write('hello');
            return new Response(200, [], $stream);
        });

        $this->mockRequest('GET', '/');
        $response = $router->resolve();

        static::assertSame(200, $response->getStatusCode());
        static::assertSame('index', (string) $response->getBody());

        $this->mockRequest('GET', '/?foo=bar');
        $response = $router->resolve();

        static::assertSame(200, $response->getStatusCode());
        static::assertSame('index', (string) $response->getBody());

        $this->mockRequest('GET', '/index.php');
        $response = $router->resolve();

        static::assertSame(404, $response->getStatusCode());

        $this->mockRequest('HEAD', '/');
        $response = $router->resolve();

        static::assertSame(200, $response->getStatusCode());
        static::assertEmpty((string) $response->getBody());

        $this->mockRequest('GET', '/hello/');
        $response = $router->resolve();

        static::assertSame(200, $response->getStatusCode());
        static::assertSame('hello', (string) $response->getBody());

        // method not allowed
        $this->mockRequest('POST', '/');
        $response = $router->resolve();

        static::assertSame(405, $response->getStatusCode());
        static::assertEmpty((string) $response->getBody());
        static::assertSame('Method Not Allowed', $response->getReasonPhrase());
    }

    public function testRegexRoute() : void
    {
        $router = (new NanoRouterMock(Response::class))
            ->addRouteRegex(['HEAD', 'GET'], '~/test(.*).php~', function () {
                if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
                    return new Response(200);
                }

                $stream = new Stream();
                $stream->write('test regex');
                return new Response(200, [], $stream);
            });

        $this->mockRequest('GET', '/test.php');
        $response = $router->resolve();

        static::assertSame(200, $response->getStatusCode());
        static::assertSame('test regex', (string) $response->getBody());

        $this->mockRequest('HEAD', '/test.php');
        $response = $router->resolve();

        static::assertSame(200, $response->getStatusCode());
        static::assertEmpty((string) $response->getBody());

        $this->mockRequest('GET', '/test2.php');
        $response = $router->resolve();

        static::assertSame(200, $response->getStatusCode());
        static::assertSame('test regex', (string) $response->getBody());

        $this->mockRequest('GET', '/tes.php');
        $response = $router->resolve();

        static::assertEquals(new Response(404), $response);

        $this->mockRequest('POST', '/test.php');
        $response = $router->resolve();

        static::assertSame(405, $response->getStatusCode());
    }

    public function testDefaultRouteExceptionHandler() : void
    {
        $router = new NanoRouterMock(Response::class);

        $router->addRoute('GET', '/', function () : ResponseInterface {
            throw new RouteException('test', 403);
        });

        $this->mockRequest('GET', '/');

        $response = $router->resolve();

        static::assertSame(403, $response->getStatusCode());
        static::assertEmpty((string) $response->getBody());
        static::expectOutputString('Tests\NanoRouterTest - FAILED - 403 test');
    }

    public function testCustomRouteExceptionHandler() : void
    {
        $router = new NanoRouterMock(Response::class, static::routeExceptionHandler(...));

        $router->addRoute('GET', '/', function () : ResponseInterface {
            throw new RouteException('test', 403);
        });

        $this->mockRequest('GET', '/');

        $response = $router->resolve();

        static::assertSame(403, $response->getStatusCode());
        static::assertEmpty((string) $response->getBody());
        static::expectOutputString('route exception handler called');
    }

    public function testNoRouteExceptionHandler() : void
    {
        $router = new NanoRouterMock(Response::class, false);

        $router->addRoute('GET', '/', function () : ResponseInterface {
            throw new RouteException('test', 403);
        });

        $this->mockRequest('GET', '/');

        $response = $router->resolve();

        static::assertSame(403, $response->getStatusCode());
        static::assertEmpty((string) $response->getBody());
        static::expectOutputString('');
    }

    public function testDefaultExceptionHandler() : void
    {
        $router = new NanoRouterMock(Response::class);

        $router->addRoute('GET', '/', function () : ResponseInterface {
            throw new Exception('test', 403);
        });

        $this->mockRequest('GET', '/');

        $response = $router->resolve();

        static::assertSame(403, $response->getStatusCode());
        static::assertEmpty((string) $response->getBody());
        static::expectOutputString('');

        $router = new NanoRouterMock(Response::class);

        $router->addRoute('GET', '/', function () : ResponseInterface {
            throw new Exception('test');
        });

        $this->mockRequest('GET', '/');

        static::expectException(Exception::class);
        static::expectExceptionMessage('test');

        $router->resolve();
    }

    public function testNoExceptionHandler() : void
    {
        $router = new NanoRouterMock(Response::class, true, false);

        $router->addRoute('GET', '/', function () : ResponseInterface {
            throw new Exception('test', 403);
        });

        static::expectException(Exception::class);
        static::expectExceptionMessage('test');

        $router->resolve();
    }

    public function testPreMiddleware() : void
    {
        $router = (new NanoRouterMock(Response::class))
            ->addMiddleware('GET', '~/api/~', 'pre', function () : ?ResponseInterface {
                return null;
            })
            ->addMiddleware('GET', '~/api/~', 'pre', function () : ?ResponseInterface {
                if (!isset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'])) {
                    return new Response(401, ['WWW-Authenticate' => 'Basic']);
                }

                return null;
            });

        $this->mockRequest('GET', '/api/test.php');
        $response = $router->resolve();

        static::assertSame(401, $response->getStatusCode());
        static::assertTrue($response->hasHeader('WWW-Authenticate'));
    }

    public function testPreMiddlewareRouteException() : void
    {
        $router = (new NanoRouterMock(Response::class, static::routeExceptionHandler(...)))
            ->addMiddleware('GET', '~/api/~', 'pre', function () : ?ResponseInterface {
                throw new RouteException('not authorized', 401);
            });

        $this->mockRequest('GET', '/api/test.php');

        static::expectOutputString('route exception handler called');

        $response = $router->resolve();

        static::assertSame(401, $response->getStatusCode());
        static::assertEmpty((string) $response->getBody());
    }

    public function testPreMiddlewareHandledException() : void
    {
        $router = (new NanoRouterMock(Response::class, true, static::exceptionHandler(...)))
            ->addMiddleware('GET', '~/api/~', 'pre', function () : ?ResponseInterface {
                throw new Exception('fatal error', 500);
            });

        $this->mockRequest('GET', '/api/test.php');

        static::expectOutputString('exception handler called');

        $response = $router->resolve();

        static::assertSame(500, $response->getStatusCode());
        static::assertEmpty((string) $response->getBody());
    }

    public function testPreMiddlewareThrownException() : void
    {
        $router = (new NanoRouterMock(Response::class, static::routeExceptionHandler(...), static::exceptionHandlerThrow(...)))
            ->addMiddleware('GET', '~/api/~', 'pre', function () : ?ResponseInterface {
                throw new Exception('fatal error', 500);
            });

        $this->mockRequest('GET', '/api/test.php');

        static::expectOutputString('exception handler called');

        static::expectException(Exception::class);
        static::expectExceptionMessage('fatal error');

        $router->resolve();
    }

    public function testPostMiddleware() : void
    {
        $router = (new NanoRouterMock(Response::class))
            ->addMiddleware('GET', '~/api/~', 'pre', function () : ?ResponseInterface {
                return null;
            })
            ->addMiddleware('GET', '~(.*)~', 'post', function (ResponseInterface $response) : ResponseInterface {
                return $response->withHeader('X-Test', 'test');
            })
            ->addMiddleware('GET', '~~', 'post', function (ResponseInterface $response) : ResponseInterface {
                return $response->withHeader('X-Powered-By', '8ctopus');
            });

        $this->mockRequest('GET', '/test.php');

        static::expectOutputString('');

        $response = $router->resolve();

        static::assertSame(404, $response->getStatusCode());
        static::assertTrue($response->hasHeader('X-Test'));
        static::assertSame('8ctopus', $response->getHeaderLine('X-Powered-By'));
    }

    public function testPostMiddlewareRouteException() : void
    {
        $router = (new NanoRouterMock(Response::class, static::routeExceptionHandler(...)))
            ->addMiddleware('GET', '~/api/~', 'post', function () : ?ResponseInterface {
                throw new RouteException('not authorized', 401);
            });

        $this->mockRequest('GET', '/api/test.php');

        static::expectOutputString('route exception handler called');

        $response = $router->resolve();

        static::assertSame(401, $response->getStatusCode());
        static::assertEmpty((string) $response->getBody());
    }

    public function testPostMiddlewareException() : void
    {
        $router = (new NanoRouterMock(Response::class, true, static::exceptionHandler(...)))
            ->addMiddleware('GET', '~/api/~', 'post', function () : ?ResponseInterface {
                throw new Exception('fatal error', 500);
            });

        $this->mockRequest('GET', '/api/test.php');

        static::expectOutputString('exception handler called');

        $response = $router->resolve();

        static::assertSame(500, $response->getStatusCode());
        static::assertEmpty((string) $response->getBody());
    }

    public function testErrorHandler() : void
    {
        $router = new NanoRouterMock(Response::class);

        $router->addErrorHandler(404, function () : ResponseInterface {
            $stream = new Stream();
            $stream->write('This page does not exist on the server');
            return new Response(404, [], $stream);
        });

        $this->mockRequest('GET', '/test.php');

        $response = $router->resolve();

        static::assertSame(404, $response->getStatusCode());
        static::assertSame('Not Found', $response->getReasonPhrase());
        static::assertSame('This page does not exist on the server', (string) $response->getBody());
    }

    public function testRouteInvalidRegex() : void
    {
        $router = new NanoRouterMock(Response::class);

        static::expectException(NanoRouterException::class);
        static::expectExceptionMessage('invalid regex');

        $router->addRouteRegex('GET', '~/test(.*)\.php', function () : void {});
    }

    public function testMiddlewareInvalidRegex() : void
    {
        $router = new NanoRouterMock(Response::class);

        static::expectException(NanoRouterException::class);
        static::expectExceptionMessage('invalid regex');

        $router->addMiddleware('GET', '~/test(.*)\.php', 'post', function () : void {});
    }

    public function testMiddlewareInvalidWhen() : void
    {
        $router = new NanoRouterMock(Response::class);

        static::expectException(NanoRouterException::class);
        static::expectExceptionMessage('invalid when clause');

        $router->addMiddleware('GET', '~/test(.*)\.php~', 'after', function () : void {});
    }

    public static function routeExceptionHandler(RouteException $exception) : void
    {
        $exception = $exception;
        echo 'route exception handler called';
    }

    public static function exceptionHandler(Exception $exception) : ?ResponseInterface
    {
        $exception = $exception;
        echo 'exception handler called';
        return new Response($exception->getCode());
    }

    public static function exceptionHandlerThrow(Exception $exception) : ?ResponseInterface
    {
        $exception = $exception;
        echo 'exception handler called';
        return null;
    }

    private function mockRequest($method, $uri) : void
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;
    }
}

class NanoRouterMock extends NanoRouter
{
    protected static function errorLog(string $message) : void
    {
        echo $message;
    }
}