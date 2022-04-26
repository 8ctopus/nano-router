<?php

/**
 * Nano router
 *
 * @author 8ctopus <hello@octopuslabs.io>
 */

namespace oct8pus\NanoRouter;

class NanoRouter
{
    private array $routes;

    public function __construct()
    {
        $this->routes = [];
    }

    /**
     * Resolve route
     *
     * @return void
     */
    public function resolve() : void
    {
        $requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        foreach ($this->routes as $routePath => $route) {
            if (!$route['regex']) {
                if ($requestPath === $routePath) {
                    if (in_array($route['type'], ['*', $_SERVER['REQUEST_METHOD']], true)) {
                        // call route
                        $route['callback']();

                        return;
                    } else {
                        // method not allowed
                        http_response_code(405);
                    }
                }
            } else {
                $matches = null;

                if (preg_match($routePath, $requestPath, $matches) === 1) {
                    if (in_array($route['type'], ['*', $_SERVER['REQUEST_METHOD']], true)) {
                        // call route
                        $route['callback']($matches);

                        return;
                    } else {
                        // method not allowed
                        http_response_code(405);
                    }
                }
            }
        }

        if ($this->routes['404']) {
            // call route
            $route['callback']();
        } else {
            // route not found
            http_response_code(404);
        }
    }

    /**
     * Add route
     *
     * @param string   $type
     * @param string   $path
     * @param callable $callback
     *
     * @return void
     */
    public function addRoute(string $type, string $path, callable $callback) : void
    {
        $this->routes[$path] = [
            'type' => $type,
            'callback' => $callback,
            'regex' => false,
        ];
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
     * @return void
     */
    public function addRouteRegex(string $type, string $path, callable $callback) : void
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
    }
}
