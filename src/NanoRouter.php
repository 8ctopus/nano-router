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
    protected array $errorHandlers;

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
        $this->errorHandlers = [];

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
            if (!$route->pathMatches($path)) {
                continue;
            }

            $match = true;

            if ($route->methodMatches($method)) {
                try {
                    $response = $route->call($request);
                } catch (Throwable $exception) {
                    $response = $this->handleException($exception);
                }

                break;
            }
        }

        if (!isset($response)) {
            $response = $this->handleError(isset($match) ? 405 : 404, $request);
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
     * Add error handler
     *
     * @param int|array $codes - zero to handle all errors
     * @param callable $handler
     *
     * @return self
     */
    public function addErrorHandler(int|array $codes, callable $handler) : self
    {
        if (!is_array($codes)) {
            $codes = [$codes];
        }

        foreach ($codes as $code) {
            $this->errorHandlers[$code] = $handler;
        }

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
                    $response = $this->handleException($exception);
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
                    $response = $this->handleException($exception);
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
     * Handle exception
     *
     * @param Throwable $exception
     *
     * @return ResponseInterface
     *
     * @throws Throwable
     */
    protected function handleException(Throwable $exception) : ResponseInterface
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
     * Handle route exception
     *
     * @param RouteException $exception
     *
     * @return void
     */
    protected function handleRouteException(RouteException $exception) : void
    {
        $trace = $exception->getTrace();

        $where = '';

        if (count($trace)) {
            $where = array_key_exists('class', $trace[0]) ? $trace[0]['class'] : $trace[0]['function'];
        }

        static::errorLog("{$where} - FAILED - {$exception->getCode()} {$exception->getMessage()}");
    }

    /**
     * Handle exception
     *
     * @param Throwable $exception
     *
     * @return ?ResponseInterface
     */
    protected function exceptionHandler(Throwable $exception) : ?ResponseInterface
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
        // specific handler for this error
        $handler = array_key_exists($error, $this->errorHandlers) ? $this->errorHandlers[$error] : null;

        if (!$handler) {
            // generic handler
            $handler = array_key_exists(0, $this->errorHandlers) ? $this->errorHandlers[0] : null;
        }

        if ($handler) {
            return call_user_func($handler, $request, $error);
        }

        return new $this->responseClass($error);
    }
}
