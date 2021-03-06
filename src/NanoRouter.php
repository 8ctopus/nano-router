<?php

/**
 * Nano router
 *
 * @author 8ctopus <hello@octopuslabs.io>
 */

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
     * @return self
     */
    public function resolve() : self
    {
        $requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        foreach ($this->routes as $routePath => $route) {
            if (!$route['regex']) {
                if ($requestPath === $routePath) {
                    if (in_array($route['type'], ['*', $_SERVER['REQUEST_METHOD']], true)) {
                        // call route
                        $route['callback']();
                    } else {
                        $this->error(405);
                    }

                    return $this;
                }
            } else {
                $matches = null;

                if (preg_match($routePath, $requestPath, $matches) === 1) {
                    if (in_array($route['type'], ['*', $_SERVER['REQUEST_METHOD']], true)) {
                        // call route
                        $route['callback']($matches);
                    } else {
                        $this->error(405);
                    }

                    return $this;
                }
            }
        }

        $this->error(404);
        return $this;
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
     * @throws Exception if regex is invalid
     *
     * @return self
     */
    public function addRouteRegex(string $type, string $path, callable $callback) : self
    {
        // validate regex
        if (!is_int(@preg_match($path, ''))) {
            throw new \Exception('invalid regex');
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
     * @param int $error
     *
     * @return void
     */
    private function error(int $error) : void
    {
        $handler = $this->errors[$error] ?? null;

        if ($handler) {
            // call route
            $handler['callback']();
        } else {
            http_response_code($error);
        }
    }
}
