<?php

declare(strict_types=1);

namespace Oct8pus\NanoRouter;

class NanoRouter
{
    private array $routes;
    private array $errors;

    public function __construct()
    {
        $this->routes = [];
        $this->errors = [];
    }

    /**
     * Resolve route
     *
     * @return Response
     */
    public function resolve() : Response
    {
        $requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        foreach ($this->routes as $routePath => $route) {
            if (!$route['regex']) {
                if ($requestPath === $routePath) {
                    if (in_array($route['type'], ['*', $_SERVER['REQUEST_METHOD']], true)) {
                        // call route
                        return $route['callback']();
                    } else {
                        return $this->error(405, $requestPath);
                    }
                }
            } else {
                $matches = null;

                if (preg_match($routePath, $requestPath, $matches) === 1) {
                    if (in_array($route['type'], ['*', $_SERVER['REQUEST_METHOD']], true)) {
                        // call route
                        return $route['callback']($matches);
                    } else {
                        return $this->error(405, $requestPath);
                    }
                }
            }
        }

        return $this->error(404, $requestPath);
    }

    /**
     * Add route
     *
     * @param string   $type
     * @param string   $path
     * @param callable $callback
     *
     * @return self
     */
    public function addRoute(string $type, string $path, callable $callback) : self
    {
        $this->routes[$path] = [
            'type' => $type,
            'callback' => $callback,
            'regex' => false,
        ];

        return $this;
    }

    /**
     * Add regex route
     *
     * @param string   $type
     * @param string   $path
     * @param callable $callback
     *
     * @return self
     *
     * @throws NanoRouterException if regex is invalid
     */
    public function addRouteRegex(string $type, string $path, callable $callback) : self
    {
        // validate regex
        if (!is_int(@preg_match($path, ''))) {
            throw new NanoRouterException('invalid regex');
        }

        $this->routes[$path] = [
            'type' => $type,
            'callback' => $callback,
            'regex' => true,
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
     * Deal with error
     *
     * @param int    $error
     * @param string $requestPath
     *
     * @return Response
     */
    private function error(int $error, string $requestPath) : Response
    {
        $handler = array_key_exists($error, $this->errors) ? $this->errors[$error] : null;

        if ($handler) {
            // call route
            return $handler['callback']($requestPath);
        } else {
            return new Response($error);
        }
    }
}
