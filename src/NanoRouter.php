<?php

declare(strict_types=1);

namespace Oct8pus\NanoRouter;

use Psr\Http\Message\ResponseInterface;

class NanoRouter
{
    private string $class;

    private array $routes;
    private array $middleware;
    private array $errors;

    /**
     * Constructor
     *
     * @param string $class ResponseInterface implementation
     */
    public function __construct(string $class)
    {
        $this->class = $class;

        $this->routes = [];
        $this->middleware = [];
        $this->errors = [];
    }

    /**
     * Resolve route
     *
     * @return ResponseInterface
     */
    public function resolve() : ResponseInterface
    {
        $requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        foreach ($this->middleware as $middleware) {
            foreach ($middleware as $regex => $route) {
                if ($route['when'] !== 'pre') {
                    continue;
                }

                if ($this->routeMatches($regex, true, $requestPath) && $this->methodMatches($route['method'])) {
                    // call middleware
                    $response = $route['callback']();

                    if ($response instanceof ResponseInterface) {
                        return $response;
                    }
                }
            }
        }

        foreach ($this->routes as $regex => $route) {
            if ($this->routeMatches($regex, $route['regex'], $requestPath)) {
                if ($this->methodMatches($route['method'])) {
                    // call route
                    try {
                        $response = $route['callback']();
                    } catch (RouteException $exception) {
                        $headers = $exception->debug() ? ['reason' => $exception->getMessage()] : [];
                        $response = new $this->class($exception->getCode(), $headers);
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

        foreach ($this->middleware as $middleware) {
            foreach ($middleware as $regex => $route) {
                if ($route['when'] !== 'post') {
                    continue;
                }

                if ($this->routeMatches($regex, true, $requestPath) && $this->methodMatches($route['method'])) {
                    // call middleware
                    $response = $route['callback']($response);
                }
            }
        }

        return $response;
    }

    /**
     * Add route
     *
     * @param string|array   $methods
     * @param string   $path
     * @param callable $callback
     *
     * @return self
     */
    public function addRoute($methods, string $path, callable $callback) : self
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
     * @param string|array   $methods
     * @param string   $regex
     * @param callable $callback
     *
     * @return self
     *
     * @throws NanoRouterException if regex is invalid
     */
    public function addRouteRegex($methods, string $regex, callable $callback) : self
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
     * @param string   $when - pre or post
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
            ]
        ];

        return $this;
    }

    /**
     * Check if route matches
     *
     * @param string $route
     * @param bool $regex
     * @param string $requestPath
     *
     * @return bool
     */
    private function routeMatches(string $route, bool $regex, string $requestPath) : bool
    {
        return (!$regex && $requestPath === $route) ||
        ($regex && preg_match($route, $requestPath, $matches) === 1);
    }

    /**
     * Check if method matches
     *
     * @param  string|array  $methods
     * @return [type]
     */
    private function methodMatches($methods) : bool
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
