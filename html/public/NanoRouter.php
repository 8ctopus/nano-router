<?php 

/**
 * Nano router
 * @author 8ctopus <hello@octopuslabs.io>
 */

class NanoRouter
{
    private array $routes;

    public function __construct()
    {
        $this->routes = [];
    }

    /**
     * Resolve route
     * @return void
     */
    public function resolve() : void
    {
        $requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        foreach ($this->routes as $routePath => $route) {
            if (!$route['regex']) {
                if ($requestPath === $routePath) {
                    // call route
                    $route['callback']();

                    return;
                }
            } else {
                if (preg_match($routePath, $requestPath) === 1) {
                    // call route
                    $route['callback']();

                    return;
                }
            }
        }
    }

    /**
     * Add route
     * @param string   $path
     * @param callable $callback
     * @return void
     */
    public function addRoute(string $path, callable $callback) : void
    {
        $this->routes[$path] = [
            'callback' => $callback,
            'regex' => false,
        ];
    }

    /**
     * Add regex route
     * @param string   $path
     * @param callable $callback
     * @return void
     */
    public function addRouteRegex(string $path, callable $callback) : void
    {
        $this->routes[$path] = [
            'callback' => $callback,
            'regex' => true,
        ];
    }
}
