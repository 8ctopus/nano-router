<?php

declare(strict_types=1);

namespace Oct8pus\NanoRouter;

use Exception;
use Psr\Http\Message\ResponseInterface;

class NanoRouter
{
    private string $class;
    private static string $staticClass;

    private array $routes;
    private array $middleware;
    private array $errors;

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
     * @param string        $class            ResponseInterface implementation
     * @param bool|callable $onRouteException
     * @param bool|callable $onException
     */
    public function __construct(string $class, bool|callable $onRouteException = true, bool|callable $onException = true)
    {
        $this->class = $class;
        static::$staticClass = $class;

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
        $requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        $response = $this->preMiddleware($requestPath);

        if ($response) {
            // send response to post request middleware so it has a chance to process the request
            return $this->postMiddleware($response, $requestPath);
        }

        foreach ($this->routes as $regex => $route) {
            if ($this->routeMatches($regex, $route['type'], $requestPath)) {
                if ($this->methodMatches($route['method'])) {
                    // call route
                    try {
                        $response = $route['callback']();
                    } catch (Exception $exception) {
                        $response = $this->handleExceptions($exception);
                    }

                    break;
                } else {
                    $response = $this->error(405, $requestPath);
                    break;
                }
            }
        }

        if (!isset($response)) {
            $response = $this->error(404, $requestPath);
        }

        return $this->postMiddleware($response, $requestPath);
    }

    /**
     * Add route
     *
     * @param array|string $methods
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
     * @param array|string $methods
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
     * @param array|string $methods
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
        $this->errors[$error] = [
            'callback' => $handler,
        ];

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
     * Handle route exceptions
     *
     * @param RouteException $exception
     *
     * @return void
     */
    public static function routeExceptionHandler(RouteException $exception) : void
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
    public static function exceptionHandler(Exception $exception) : ?ResponseInterface
    {
        $code = $exception->getCode();

        if ($code >= 200 && $code < 600) {
            return new static::$staticClass($code);
        }

        return null;
    }

    /**
     * Pre request middleware
     *
     * @param string $requestPath
     *
     * @return ?ResponseInterface
     *
     * @note only first matching pre request middlware will be executed
     */
    protected function preMiddleware(string $requestPath) : ?ResponseInterface
    {
        foreach ($this->middleware as $middleware) {
            foreach ($middleware as $regex => $route) {
                if ($route['when'] !== 'pre') {
                    continue;
                }

                if ($this->routeMatches($regex, 'regex', $requestPath) && $this->methodMatches($route['method'])) {
                    // call middleware
                    try {
                        $response = $route['callback']();
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
     * @param ResponseInterface $response
     * @param string            $requestPath
     *
     * @return ?ResponseInterface
     *
     * @note all matching post request middleware will be executed
     */
    protected function postMiddleware(ResponseInterface $response, string $requestPath) : ?ResponseInterface
    {
        foreach ($this->middleware as $middleware) {
            foreach ($middleware as $regex => $route) {
                if ($route['when'] !== 'post') {
                    continue;
                }

                if ($this->routeMatches($regex, 'regex', $requestPath) && $this->methodMatches($route['method'])) {
                    // call middleware
                    try {
                        $response = $route['callback']($response);
                    } catch (Exception $exception) {
                        $response = $this->handleExceptions($exception);
                    }
                }
            }
        }

        return isset($response) ? $response : null;
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
    private function routeMatches(string $route, string $type, string $requestPath) : bool
    {
        switch ($type) {
            case 'exact':
                return $requestPath === $route;

            case 'starts':
                return str_starts_with($requestPath, $route);

            case 'regex':
                return preg_match($route, $requestPath, $matches) === 1;

            default:
                // @codeCoverageIgnoreStart
                throw new NanoRouterException('invalid route type');
                // @codeCoverageIgnoreEnd
        }
    }

    /**
     * Check if method matches
     *
     * @param array|string $methods
     *
     * @return [type]
     */
    private function methodMatches(string|array $methods) : bool
    {
        if ($methods === '*') {
            return true;
        }

        if (is_string($methods)) {
            $methods = [$methods];
        }

        return in_array($_SERVER['REQUEST_METHOD'], $methods, true);
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
    private function handleExceptions(Exception $exception) : ResponseInterface
    {
        // route exceptions always return an error response
        if ($exception instanceof RouteException) {
            if (is_callable($this->onRouteException)) {
                call_user_func($this->onRouteException, $exception);
            }

            return new $this->class($exception->getCode(), []);
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
     * Deal with error
     *
     * @param int    $error
     * @param string $requestPath
     *
     * @return ResponseInterface
     */
    private function error(int $error, string $requestPath) : ResponseInterface
    {
        $handler = array_key_exists($error, $this->errors) ? $this->errors[$error] : null;

        if ($handler) {
            // call route
            return $handler['callback']($requestPath);
        } else {
            return new $this->class($error);
        }
    }
}
