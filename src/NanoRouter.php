<?php

declare(strict_types=1);

namespace Oct8pus\NanoRouter;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

class NanoRouter
{
    protected string $responseClass;
    protected string $srFactoryClass;

    /**
     * @var array<Route>
     */
    protected array $routes;

    /**
     * @var array<Middleware>
     */
    protected array $middleware;

    /**
     * @var array<int, callable>
     */
    protected array $routeExceptionHandlers;

    /**
     * @var ?callable
     */
    private $onRouteException;

    /**
     * @var ?callable
     */
    private $onException;

    /**
     * Constructor
     *
     * @param string        $response             ResponseInterface implementation
     * @param string        $serverRequestFactory ServerRequestFactoryInterface implementation
     * @param bool|callable $onRouteException
     * @param bool|callable $onException
     */
    public function __construct(string $response, string $serverRequestFactory, bool|callable $onRouteException = true, bool|callable $onException = true)
    {
        $this->responseClass = $response;
        $this->srFactoryClass = $serverRequestFactory;

        $this->routes = [];
        $this->middleware = [];
        $this->routeExceptionHandlers = [];

        if (is_callable($onRouteException)) {
            $this->onRouteException = $onRouteException;
        } elseif ($onRouteException === false) {
            $this->onRouteException = null;
        } else {
            $this->onRouteException = self::handleRouteException(...);
        }

        if (is_callable($onException)) {
            $this->onException = $onException;
        } elseif ($onException === false) {
            $this->onException = null;
        } else {
            $this->onException = self::handleException(...);
        }
    }

    /**
     * Resolve route
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function resolve(ServerRequestInterface $request) : ResponseInterface
    {
        $response = $this->preMiddleware($request);

        if ($response) {
            // send response to post request middleware so it has a chance to process the request
            return $this->postMiddleware($response, $request);
        }

        $path = $request->getUri()->getPath();
        $method = $request->getMethod();

        foreach ($this->routes as $route) {
            if (!$route->pathMatches($path)) {
                continue;
            }

            $match = true;

            if ($route->methodMatches($method)) {
                try {
                    $response = $route->call($request);
                } catch (Throwable $exception) {
                    $response = $this->handleExceptions($exception, $request);
                }

                break;
            }
        }

        if (!isset($response)) {
            $response = $this->handleRouteException(new RouteException('', isset($match) ? 405 : 404), $request);
        }

        return $this->postMiddleware($response, $request);
    }

    /**
     * Add route
     *
     * @param Route $route
     *
     * @return self
     */
    public function addRoute(Route $route) : self
    {
        $this->routes[] = $route;
        return $this;
    }

    /**
     * Add middleware
     *
     * @param array<string>|string $methods
     * @param string               $regex
     * @param MiddlewareType       $type
     * @param callable             $callback
     *
     * @return self
     *
     * @throws NanoRouterException if regex is invalid
     */
    public function addMiddleware(array|string $methods, string $regex, MiddlewareType $type, callable $callback) : self
    {
        $this->middleware[] = new Middleware($type, $methods, $regex, $callback);
        return $this;
    }

    /**
     * Pre request middleware
     *
     * @param ServerRequestInterface $request
     *
     * @return ?ResponseInterface
     *
     * @note only first matching pre request middlware will be executed
     */
    protected function preMiddleware(ServerRequestInterface $request) : ?ResponseInterface
    {
        foreach ($this->middleware as $middleware) {
            if ($middleware->type() !== MiddlewareType::Pre) {
                continue;
            }

            if ($middleware->pathMatches($request->getUri()->getPath()) && $middleware->methodMatches($request->getMethod())) {
                // call middleware
                try {
                    $response = $middleware->callPre($request);
                } catch (Throwable $exception) {
                    $response = $this->handleExceptions($exception, $request);
                }

                if ($response instanceof ResponseInterface) {
                    return $response;
                }
            }
        }

        return null;
    }

    /**
     * Post request middleware
     *
     * @param ResponseInterface      $response
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     *
     * @note all matching post request middleware will be executed
     */
    protected function postMiddleware(ResponseInterface $response, ServerRequestInterface $request) : ResponseInterface
    {
        foreach ($this->middleware as $middleware) {
            if ($middleware->type() !== MiddlewareType::Post) {
                continue;
            }

            if ($middleware->pathMatches($request->getUri()->getPath()) && $middleware->methodMatches($request->getMethod())) {
                // call middleware
                try {
                    $response = $middleware->callPost($response, $request);
                } catch (Throwable $exception) {
                    $response = $this->handleExceptions($exception, $request);
                }
            }
        }

        return $response;
    }

    /**
     * Add route exception handler
     *
     * @param int|array $codes
     * @param callable $handler
     *
     * @return self
     */
    public function addRouteExceptionHandler(int|array $codes, callable $handler) : self
    {
        if (!is_array($codes)) {
            $codes = [$codes];
        }

        foreach ($codes as $code) {
            $this->routeExceptionHandlers[$code] = $handler;
        }

        return $this;
    }

    /**
     * Handle exceptions
     *
     * @param Throwable $exception
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     *
     * @throws Throwable
     */
    protected function handleExceptions(Throwable $exception, ServerRequestInterface $request) : ResponseInterface
    {
        // route exceptions always return an error response
        if ($exception instanceof RouteException) {
            if (is_callable($this->onRouteException)) {
                $response = call_user_func($this->onRouteException, $exception, $request);

                if ($response) {
                    return $response;
                }
            }

            return new $this->responseClass($exception->getCode());
        }

        // exceptions can be converted to a response, if not throw
        if (is_callable($this->onException)) {
            $response = call_user_func($this->onException, $exception);

            if ($response) {
                return $response;
            }
        }

        // otherwise the exception is rethrown
        throw $exception;
    }

    /**
     * Handle route exception
     *
     * @param RouteException $exception
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    protected function handleRouteException(RouteException $exception, ServerRequestInterface $request) : ResponseInterface
    {
        $code = $exception->getCode();

        $handler = array_key_exists($code, $this->routeExceptionHandlers) ? $this->routeExceptionHandlers[$code] : null;

        if ($handler) {
            return call_user_func($handler, $request, $code);
        }

        return new $this->responseClass($code);
    }

    /**
     * Handle exception
     *
     * @param Throwable $exception
     *
     * @return ?ResponseInterface
     */
    protected function handleException(Throwable $exception) : ?ResponseInterface
    {
        $code = $exception->getCode();

        if ($code >= 200 && $code < 600) {
            return new $this->responseClass($code);
        }

        return null;
    }
}
