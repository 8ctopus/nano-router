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
     * @var array<string, array{'type': string, 'method': string|array<string>, 'callback': callable}>
     */
    protected array $routes;

    /**
     * @var array<int, array<string, array{'method': string|array<string>, 'when': string, 'callback': callable}>>
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
     * @return ResponseInterface
     */
    public function resolve() : ResponseInterface
    {
        $request = (new $this->serverRequestFactoryClass())
            ->createServerRequest(
                $_SERVER['REQUEST_METHOD'],
                $_SERVER['REQUEST_URI'],
                $_SERVER,
            );

        $response = $this->preMiddleware($request);

        if ($response) {
            // send response to post request middleware so it has a chance to process the request
            return $this->postMiddleware($response, $request);
        }

        $path = $request->getUri()->getPath();
        $method = $request->getMethod();

        foreach ($this->routes as $regex => $route) {
            if ($this->routeMatches($regex, $route['type'], $path)) {
                if ($this->methodMatches($method, $route['method'])) {
                    // call route
                    try {
                        $response = $route['callback']($request);
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
     * @param string       $path
     * @param callable     $callback
     *
     * @return self
     */
    public function addRoute(string|array $methods, string $path, callable $callback) : self
    {
        $this->routes[$path] = [
            'type' => 'exact',
            'method' => $methods,
            'callback' => $callback,
        ];

        return $this;
    }

    /**
     * Add starts with route
     *
     * @param array<string>|string $methods
     * @param string       $path
     * @param callable     $callback
     *
     * @return self
     */
    public function addRouteStartWith(string|array $methods, string $path, callable $callback) : self
    {
        $this->routes[$path] = [
            'type' => 'starts',
            'method' => $methods,
            'callback' => $callback,
        ];

        return $this;
    }

    /**
     * Add regex route
     *
     * @param array<string>|string $methods
     * @param string       $regex
     * @param callable     $callback
     *
     * @return self
     *
     * @throws NanoRouterException if regex is invalid
     */
    public function addRouteRegex(string|array $methods, string $regex, callable $callback) : self
    {
        // validate regex
        if (!is_int(@preg_match($regex, ''))) {
            throw new NanoRouterException('invalid regex');
        }

        $this->routes[$regex] = [
            'type' => 'regex',
            'method' => $methods,
            'callback' => $callback,
        ];

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
     * @param string   $method
     * @param string   $regex
     * @param string   $when     - pre or post
     * @param callable $callback
     *
     * @return self
     *
     * @throws NanoRouterException if regex is invalid
     */
    public function addMiddleware(string $method, string $regex, string $when, callable $callback) : self
    {
        // validate regex
        if (!is_int(@preg_match($regex, ''))) {
            throw new NanoRouterException('invalid regex');
        }

        if (!in_array($when, ['pre', 'post'], true)) {
            throw new NanoRouterException('invalid when clause');
        }

        $this->middleware[] = [
            $regex => [
                'method' => $method,
                'when' => $when,
                'callback' => $callback,
            ],
        ];

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
            foreach ($middleware as $regex => $route) {
                if ($route['when'] !== 'pre') {
                    continue;
                }

                if ($this->routeMatches($regex, 'regex', $request->getUri()->getPath()) && $this->methodMatches($request->getMethod(), $route['method'])) {
                    // call middleware
                    try {
                        $response = $route['callback']($request);
                    } catch (Exception $exception) {
                        $response = $this->handleExceptions($exception);
                    }

                    if ($response instanceof ResponseInterface) {
                        return $response;
                    }
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
            foreach ($middleware as $regex => $route) {
                if ($route['when'] !== 'post') {
                    continue;
                }

                if ($this->routeMatches($regex, 'regex', $request->getUri()->getPath()) && $this->methodMatches($request->getMethod(), $route['method'])) {
                    // call middleware
                    try {
                        $response = $route['callback']($response, $request);
                    } catch (Exception $exception) {
                        $response = $this->handleExceptions($exception);
                    }
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
     * Check if route matches
     *
     * @param string $route
     * @param string $type
     * @param string $requestPath
     *
     * @return bool
     */
    protected function routeMatches(string $route, string $type, string $requestPath) : bool
    {
        switch ($type) {
            case 'exact':
                return $requestPath === $route;

            case 'starts':
                return str_starts_with($requestPath, $route);

            case 'regex':
                return preg_match($route, $requestPath) === 1;

            default:
                // @codeCoverageIgnoreStart
                throw new NanoRouterException('invalid route type');
                // @codeCoverageIgnoreEnd
        }
    }

    /**
     * Check if method matches
     *
     * @param string       $method
     * @param array<string>|string $methods
     *
     * @return bool
     */
    protected function methodMatches(string $method, string|array $methods) : bool
    {
        if ($methods === '*') {
            return true;
        }

        if (is_string($methods)) {
            $methods = [$methods];
        }

        return in_array($method, $methods, true);
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
