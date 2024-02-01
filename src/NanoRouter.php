<?php

declare(strict_types=1);

namespace Oct8pus\NanoRouter;

use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class NanoRouter
{
    protected string $responseClass;
    protected string $serverRequestFactoryClass;

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
    protected array $errors;

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
        $this->serverRequestFactoryClass = $serverRequestFactory;

        $this->routes = [];
        $this->middleware = [];
        $this->errors = [];

        if (is_callable($onRouteException)) {
            $this->onRouteException = $onRouteException;
        } elseif ($onRouteException === false) {
            $this->onRouteException = null;
        } else {
            $this->onRouteException = self::routeExceptionHandler(...);
        }

        if (is_callable($onException)) {
            $this->onException = $onException;
        } elseif ($onException === false) {
            $this->onException = null;
        } else {
            $this->onException = self::exceptionHandler(...);
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
            if ($route->pathMatches($path)) {
                if ($route->methodMatches($method)) {
                    // call route
                    try {
                        $response = $route->call($request);
                    } catch (Exception $exception) {
                        $response = $this->handleExceptions($exception);
                    }

                    break;
                } else {
                    // potential response if no other route matches
                    $response = $this->handleError(405, $request);
                }
            }
        }

        if (!isset($response)) {
            $response = $this->handleError(404, $request);
        }

        return $this->postMiddleware($response, $request);
    }

    /**
     * Add route
     *
     * @param array<string>|string $methods
     * @param string               $path
     * @param callable             $callback
     *
     * @return self
     */
    public function addRoute(array|string $methods, string $path, callable $callback) : self
    {
        $this->routes[] = new Route(RouteType::Exact, $methods, $path, $callback);
        return $this;
    }

    /**
     * Add starts with route
     *
     * @param array<string>|string $methods
     * @param string               $path
     * @param callable             $callback
     *
     * @return self
     */
    public function addRouteStartsWith(array|string $methods, string $path, callable $callback) : self
    {
        $this->routes[] = new Route(RouteType::StartsWith, $methods, $path, $callback);
        return $this;
    }

    /**
     * Add regex route
     *
     * @param array<string>|string $methods
     * @param string               $regex
     * @param callable             $callback
     *
     * @return self
     *
     * @throws NanoRouterException if regex is invalid
     */
    public function addRouteRegex(array|string $methods, string $regex, callable $callback) : self
    {
        $this->routes[] = new Route(RouteType::Regex, $methods, $regex, $callback);
        return $this;
    }

    /**
     * Add error handler
     *
     * @param int      $error
     * @param callable $handler
     *
     * @return self
     */
    public function addErrorHandler(int $error, callable $handler) : self
    {
        $this->errors[$error] = $handler;
        return $this;
    }

    /**
     * Add middleware
     *
     * @param array|string   $methods
     * @param string         $regex
     * @param MiddlewareType $type
     * @param callable       $callback
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
                    $response = $middleware->call($request);
                } catch (Exception $exception) {
                    $response = $this->handleExceptions($exception);
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
                    $response = $middleware->call($response, $request);
                } catch (Exception $exception) {
                    $response = $this->handleExceptions($exception);
                }
            }
        }

        return $response;
    }

    protected static function errorLog(string $message) : void
    {
        // @codeCoverageIgnoreStart
        error_log($message);
        // @codeCoverageIgnoreEnd
    }

    /**
     * Handle exceptions
     *
     * @param Exception $exception
     *
     * @return ResponseInterface
     *
     * @throws Exception
     */
    protected function handleExceptions(Exception $exception) : ResponseInterface
    {
        // route exceptions always return an error response
        if ($exception instanceof RouteException) {
            if (is_callable($this->onRouteException)) {
                call_user_func($this->onRouteException, $exception);
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
     * Handle route exceptions
     *
     * @param RouteException $exception
     *
     * @return void
     */
    protected function routeExceptionHandler(RouteException $exception) : void
    {
        $trace = $exception->getTrace();

        $where = '';

        if (count($trace)) {
            $where = array_key_exists('class', $trace[0]) ? $trace[0]['class'] : $trace[0]['function'];
        }

        static::errorLog("{$where} - FAILED - {$exception->getCode()} {$exception->getMessage()}");
    }

    /**
     * Handle exceptions
     *
     * @param Exception $exception
     *
     * @return ?ResponseInterface
     */
    protected function exceptionHandler(Exception $exception) : ?ResponseInterface
    {
        $code = $exception->getCode();

        if ($code >= 200 && $code < 600) {
            return new $this->responseClass($code);
        }

        return null;
    }

    /**
     * Handle error
     *
     * @param int                    $error
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    private function handleError(int $error, ServerRequestInterface $request) : ResponseInterface
    {
        $handler = array_key_exists($error, $this->errors) ? $this->errors[$error] : null;

        if ($handler) {
            // call error handler
            return call_user_func($handler, $request);
        } else {
            return new $this->responseClass($error);
        }
    }
}
