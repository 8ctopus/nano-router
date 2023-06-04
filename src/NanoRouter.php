<?php

declare(strict_types=1);

namespace Oct8pus\NanoRouter;

use Exception;
use Psr\Http\Message\ResponseInterface;

class NanoRouter
{
    private string $class;

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
     * @param string    $class            ResponseInterface implementation
     * @param ?callable $onRouteException
     * @param ?callable $onException
     */
    public function __construct(string $class, ?callable $onRouteException, ?callable $onException)
    {
        $this->class = $class;

        $this->routes = [];
        $this->middleware = [];
        $this->errors = [];

        $this->onRouteException = $onRouteException;
        $this->onException = $onException;
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
            if ($this->routeMatches($regex, $route['regex'], $requestPath)) {
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
            'regex' => false,
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
            'regex' => true,
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

                if ($this->routeMatches($regex, true, $requestPath) && $this->methodMatches($route['method'])) {
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

                if ($this->routeMatches($regex, true, $requestPath) && $this->methodMatches($route['method'])) {
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

    /**
     * Check if route matches
     *
     * @param string $route
     * @param bool   $regex
     * @param string $requestPath
     *
     * @return bool
     */
    private function routeMatches(string $route, bool $regex, string $requestPath) : bool
    {
        return (!$regex && $requestPath === $route)
        || ($regex && preg_match($route, $requestPath, $matches) === 1);
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

        // exceptions can be converted to a response
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
