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

        foreach ($this->routes as $regex => $route) {
            if (
                (!$route['regex'] && $requestPath === $regex) ||
                ($route['regex'] && preg_match($regex, $requestPath, $matches) === 1)
            ) {
                if (in_array($route['method'], ['*', $_SERVER['REQUEST_METHOD']], true)) {
                    // call route
                    $response = $route['callback']();
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
                if (preg_match($regex, $requestPath, $matches) === 1) {
                    if (in_array($route['method'], ['*', $_SERVER['REQUEST_METHOD']], true)) {
                        // call middleware
                        $response = $route['callback']($response);
                    }
                }
            }
        }

        return $response;
    }

    /**
     * Add route
     *
     * @param string   $method
     * @param string   $path
     * @param callable $callback
     *
     * @return self
     */
    public function addRoute(string $method, string $path, callable $callback) : self
    {
        $this->routes[$path] = [
            'type' => 'route',
            'regex' => false,
            'method' => $method,
            'callback' => $callback,
        ];

        return $this;
    }

    /**
     * Add regex route
     *
     * @param string   $method
     * @param string   $regex
     * @param callable $callback
     *
     * @return self
     *
     * @throws NanoRouterException if regex is invalid
     */
    public function addRouteRegex(string $method, string $regex, callable $callback) : self
    {
        // validate regex
        if (!is_int(@preg_match($regex, ''))) {
            throw new NanoRouterException('invalid regex');
        }

        $this->routes[$regex] = [
            'type' => 'route',
            'regex' => true,
            'method' => $method,
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
     * @param callable $callback
     *
     * @return self
     *
     * @throws NanoRouterException if regex is invalid
     */
    public function addMiddleware(string $method, string $regex, callable $callback) : self
    {
        // validate regex
        if (!is_int(@preg_match($regex, ''))) {
            throw new NanoRouterException('invalid regex');
        }

        $this->middleware[] = [
            $regex => [
                'method' => $method,
                'callback' => $callback,
            ]
        ];

        return $this;
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
