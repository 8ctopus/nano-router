<?php

declare(strict_types=1);

namespace Tests;

use Exception;
use HttpSoft\Message\Response;
use HttpSoft\Message\ServerRequestFactory;
use HttpSoft\Message\Stream;
use Oct8pus\NanoRouter\NanoRouter;
use Oct8pus\NanoRouter\NanoRouterException;
use Oct8pus\NanoRouter\RouteException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Tests\TestCase;

/**
 * @internal
 *
 * @covers \Oct8pus\NanoRouter\NanoRouter
 */
final class NanoRouterTest extends TestCase
{
    public function testRoute() : void
    {
        $router = new NanoRouterMock(Response::class, ServerRequestFactory::class);

        // 404
        $request = $this->mockRequest('GET', '/');
        $response = $router->resolve($request);

        self::assertSame(404, $response->getStatusCode());
        self::assertEmpty((string) $response->getBody());
        self::assertSame('Not Found', $response->getReasonPhrase());

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

        $request = $this->mockRequest('GET', '/');
        $response = $router->resolve($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('index', (string) $response->getBody());

        $request = $this->mockRequest('GET', '/', '?foo=bar');
        $response = $router->resolve($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('index', (string) $response->getBody());

        $request = $this->mockRequest('GET', '/index.php');
        $response = $router->resolve($request);

        self::assertSame(404, $response->getStatusCode());

        $request = $this->mockRequest('HEAD', '/');
        $response = $router->resolve($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertEmpty((string) $response->getBody());

        $request = $this->mockRequest('GET', '/hello/');
        $response = $router->resolve($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('hello', (string) $response->getBody());

        // method not allowed
        $request = $this->mockRequest('POST', '/');
        $response = $router->resolve($request);

        self::assertSame(405, $response->getStatusCode());
        self::assertEmpty((string) $response->getBody());
        self::assertSame('Method Not Allowed', $response->getReasonPhrase());
    }

    public function testStartsWithRoute() : void
    {
        $router = new NanoRouterMock(Response::class, ServerRequestFactory::class);

        // add index route
        $router->addRouteStartWith(['HEAD', 'GET'], '/hello/', function () : ResponseInterface {
            if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
                return new Response(200);
            }

            $stream = new Stream();
            $stream->write('hello');
            return new Response(200, [], $stream);
        });

        $request = $this->mockRequest('HEAD', '/');
        $response = $router->resolve($request);

        self::assertSame(404, $response->getStatusCode());
        self::assertEmpty((string) $response->getBody());
        self::assertSame('Not Found', $response->getReasonPhrase());

        $request = $this->mockRequest('GET', '/hello/');
        $response = $router->resolve($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('hello', (string) $response->getBody());

        $request = $this->mockRequest('GET', '/hello/test');
        $response = $router->resolve($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('hello', (string) $response->getBody());

        $request = $this->mockRequest('GET', '/hello/test/');
        $response = $router->resolve($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('hello', (string) $response->getBody());

        $request = $this->mockRequest('GET', '/hello/test/test/');
        $response = $router->resolve($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('hello', (string) $response->getBody());
    }

    public function testRegexRoute() : void
    {
        $router = (new NanoRouterMock(Response::class, ServerRequestFactory::class))
            ->addRouteRegex(['HEAD', 'GET'], '~/test(.*).php~', function () {
                if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
                    return new Response(200);
                }

                $stream = new Stream();
                $stream->write('test regex');
                return new Response(200, [], $stream);
            });

        $request = $this->mockRequest('GET', '/test.php');
        $response = $router->resolve($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('test regex', (string) $response->getBody());

        $request = $this->mockRequest('HEAD', '/test.php');
        $response = $router->resolve($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertEmpty((string) $response->getBody());

        $request = $this->mockRequest('GET', '/test2.php');
        $response = $router->resolve($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('test regex', (string) $response->getBody());

        $request = $this->mockRequest('GET', '/tes.php');
        $response = $router->resolve($request);

        self::assertEquals(new Response(404), $response);

        $request = $this->mockRequest('POST', '/test.php');
        $response = $router->resolve($request);

        self::assertSame(405, $response->getStatusCode());
    }

    public function testDefaultRouteExceptionHandler() : void
    {
        $router = new NanoRouterMock(Response::class, ServerRequestFactory::class);

        $router->addRoute('GET', '/', function () : ResponseInterface {
            throw new RouteException('test', 403);
        });

        $request = $this->mockRequest('GET', '/');
        $response = $router->resolve($request);

        self::assertSame(403, $response->getStatusCode());
        self::assertEmpty((string) $response->getBody());
        self::expectOutputString('Tests\NanoRouterTest - FAILED - 403 test');
    }

    public function testCustomRouteExceptionHandler() : void
    {
        $router = new NanoRouterMock(Response::class, ServerRequestFactory::class, self::routeExceptionHandler(...));

        $router->addRoute('GET', '/', function () : ResponseInterface {
            throw new RouteException('test', 403);
        });

        $request = $this->mockRequest('GET', '/');
        $response = $router->resolve($request);

        self::assertSame(403, $response->getStatusCode());
        self::assertEmpty((string) $response->getBody());
        self::expectOutputString('route exception handler called');
    }

    public function testNoRouteExceptionHandler() : void
    {
        $router = new NanoRouterMock(Response::class, ServerRequestFactory::class, false);

        $router->addRoute('GET', '/', function () : ResponseInterface {
            throw new RouteException('test', 403);
        });

        $request = $this->mockRequest('GET', '/');
        $response = $router->resolve($request);

        self::assertSame(403, $response->getStatusCode());
        self::assertEmpty((string) $response->getBody());
        self::expectOutputString('');
    }

    public function testDefaultExceptionHandler() : void
    {
        $router = new NanoRouterMock(Response::class, ServerRequestFactory::class);

        $router->addRoute('GET', '/', function () : ResponseInterface {
            throw new Exception('test', 403);
        });

        $request = $this->mockRequest('GET', '/');
        $response = $router->resolve($request);

        self::assertSame(403, $response->getStatusCode());
        self::assertEmpty((string) $response->getBody());
        self::expectOutputString('');

        $router = new NanoRouterMock(Response::class, ServerRequestFactory::class);

        $router->addRoute('GET', '/', function () : ResponseInterface {
            throw new Exception('test');
        });

        self::expectException(Exception::class);
        self::expectExceptionMessage('test');

        $request = $this->mockRequest('GET', '/');
        $router->resolve($request);
    }

    public function testNoExceptionHandler() : void
    {
        $router = new NanoRouterMock(Response::class, ServerRequestFactory::class, true, false);

        $router->addRoute('GET', '/', function () : ResponseInterface {
            throw new Exception('test', 403);
        });

        self::expectException(Exception::class);
        self::expectExceptionMessage('test');

        $request = $this->mockRequest('GET', '/');
        $router->resolve($request);
    }

    public function testPreMiddleware() : void
    {
        $router = (new NanoRouterMock(Response::class, ServerRequestFactory::class))
            ->addMiddleware('GET', '~/api/~', 'pre', function (ServerRequestInterface $request) : ?ResponseInterface {
                return null;
            })
            ->addMiddleware('GET', '~/api/~', 'pre', function (ServerRequestInterface $request) : ?ResponseInterface {
                $server = $request->getServerParams();

                $login = $server['PHP_AUTH_USER'] ?? null;
                $password = $server['PHP_AUTH_PW'] ?? null;

                if (!$login || $password) {
                    return new Response(401, ['WWW-Authenticate' => 'Basic']);
                }

                return null;
            });

        $request = $this->mockRequest('GET', '/api/test.php');
        $response = $router->resolve($request);

        self::assertSame(401, $response->getStatusCode());
        self::assertTrue($response->hasHeader('WWW-Authenticate'));
    }

    public function testPreMiddlewareRouteException() : void
    {
        $router = (new NanoRouterMock(Response::class, ServerRequestFactory::class, self::routeExceptionHandler(...)))
            ->addMiddleware('GET', '~/api/~', 'pre', function () : ?ResponseInterface {
                throw new RouteException('not authorized', 401);
            });

        $request = $this->mockRequest('GET', '/api/test.php');
        $response = $router->resolve($request);

        self::assertSame(401, $response->getStatusCode());
        self::assertEmpty((string) $response->getBody());
        self::expectOutputString('route exception handler called');
    }

    public function testPreMiddlewareHandledException() : void
    {
        $router = (new NanoRouterMock(Response::class, ServerRequestFactory::class, true, self::exceptionHandler(...)))
            ->addMiddleware('GET', '~/api/~', 'pre', function () : ?ResponseInterface {
                throw new Exception('fatal error', 500);
            });

        $request = $this->mockRequest('GET', '/api/test.php');
        $response = $router->resolve($request);

        self::assertSame(500, $response->getStatusCode());
        self::assertEmpty((string) $response->getBody());
        self::expectOutputString('exception handler called');
    }

    public function testPreMiddlewareThrownException() : void
    {
        $router = (new NanoRouterMock(Response::class, ServerRequestFactory::class, self::routeExceptionHandler(...), self::exceptionHandlerThrow(...)))
            ->addMiddleware('GET', '~/api/~', 'pre', function () : ?ResponseInterface {
                throw new Exception('fatal error', 500);
            });

        self::expectException(Exception::class);
        self::expectExceptionMessage('fatal error');
        self::expectOutputString('exception handler called');

        $request = $this->mockRequest('GET', '/api/test.php');
        $router->resolve($request);
    }

    public function testPostMiddleware() : void
    {
        $router = (new NanoRouterMock(Response::class, ServerRequestFactory::class))
            ->addMiddleware('GET', '~/api/~', 'pre', function () : ?ResponseInterface {
                return null;
            })
            ->addMiddleware('GET', '~(.*)~', 'post', function (ResponseInterface $response, ServerRequestInterface $request) : ResponseInterface {
                return $response->withHeader('X-Test', 'test');
            })
            ->addMiddleware('GET', '~~', 'post', function (ResponseInterface $response, ServerRequestInterface $request) : ResponseInterface {
                return $response->withHeader('X-Powered-By', '8ctopus');
            });

        $request = $this->mockRequest('GET', '/test.php');
        $response = $router->resolve($request);

        self::assertSame(404, $response->getStatusCode());
        self::assertTrue($response->hasHeader('X-Test'));
        self::assertSame('8ctopus', $response->getHeaderLine('X-Powered-By'));
        self::expectOutputString('');
    }

    public function testPostMiddlewareRouteException() : void
    {
        $router = (new NanoRouterMock(Response::class, ServerRequestFactory::class, self::routeExceptionHandler(...)))
            ->addMiddleware('GET', '~/api/~', 'post', function () : ?ResponseInterface {
                throw new RouteException('not authorized', 401);
            });

        $request = $this->mockRequest('GET', '/api/test.php');
        $response = $router->resolve($request);

        self::assertSame(401, $response->getStatusCode());
        self::assertEmpty((string) $response->getBody());
        self::expectOutputString('route exception handler called');
    }

    public function testPostMiddlewareException() : void
    {
        $router = (new NanoRouterMock(Response::class, ServerRequestFactory::class, true, self::exceptionHandler(...)))
            ->addMiddleware('GET', '~/api/~', 'post', function () : ?ResponseInterface {
                throw new Exception('fatal error', 500);
            });

        $request = $this->mockRequest('GET', '/api/test.php');
        $response = $router->resolve($request);

        self::assertSame(500, $response->getStatusCode());
        self::assertEmpty((string) $response->getBody());
        self::expectOutputString('exception handler called');
    }

    public function testErrorHandler() : void
    {
        $router = new NanoRouterMock(Response::class, ServerRequestFactory::class);

        $router->addErrorHandler(404, function (ServerRequestInterface $request) : ResponseInterface {
            $stream = new Stream();
            $stream->write('This page does not exist on the server - ' . $request->getRequestTarget());
            return new Response(404, [], $stream);
        });

        $request = $this->mockRequest('GET', '/test.php');
        $response = $router->resolve($request);

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('Not Found', $response->getReasonPhrase());
        self::assertSame('This page does not exist on the server - /test.php', (string) $response->getBody());
    }

    public function testRouteInvalidRegex() : void
    {
        $router = new NanoRouterMock(Response::class, ServerRequestFactory::class);

        self::expectException(NanoRouterException::class);
        self::expectExceptionMessage('invalid regex');

        $router->addRouteRegex('GET', '~/test(.*)\.php', function () : void {});
    }

    public function testMiddlewareInvalidRegex() : void
    {
        $router = new NanoRouterMock(Response::class, ServerRequestFactory::class);

        self::expectException(NanoRouterException::class);
        self::expectExceptionMessage('invalid regex');

        $router->addMiddleware('GET', '~/test(.*)\.php', 'post', function () : void {});
    }

    public function testMiddlewareInvalidWhen() : void
    {
        $router = new NanoRouterMock(Response::class, ServerRequestFactory::class);

        self::expectException(NanoRouterException::class);
        self::expectExceptionMessage('invalid when clause');

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
}

class NanoRouterMock extends NanoRouter
{
    protected static function errorLog(string $message) : void
    {
        echo $message;
    }
}
