<?php

declare(strict_types=1);

namespace Oct8pus\NanoRouter;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Route
{
    private readonly RouteType $type;
    private readonly array|string $methods;
    private readonly string $path;
    private $callback;

    /*
        $this->routes[$path] = [
            'type' => 'exact',
            'method' => $methods,
            'callback' => $callback,
        ];
    */

    /**
     * Constructor
     *
     * @param RouteType $type
     * @param string    $methods
     * @param string    $path
     * @param callable  $callback
     *
     * @throws NanoRouterException
     */
    public function __construct(RouteType $type, array|string $methods, string $path, callable $callback)
    {
        if ($type === RouteType::Regex && !is_int(@preg_match($path, ''))) {
            throw new NanoRouterException("invalid regex - {$path}");
        }

        $this->type = $type;
        $this->methods = $methods;
        $this->path = $path;
        $this->callback = $callback;
    }

    /**
     * Check if route matches
     *
     * @param  string $method
     * @param  string $path
     *
     * @return bool
     */
    public function matches(string $method, string $path) : bool
    {
        return $this->pathMatches($path) && $this->methodMatches($method);
    }

    /**
     * Check if path matches
     *
     * @param  string $path
     *
     * @return bool
     */
    public function pathMatches(string $path) : bool
    {
        switch ($this->type) {
            case RouteType::Exact:
                return $this->path === $path;

            case RouteType::StartsWith:
                return str_starts_with($path, $this->path);

            case RouteType::Regex:
                return preg_match($this->path, $path) === 1;

            default:
                throw new NanoRouterException("Unknown route type - {$this->type}");
        }
    }

    /**
     * Check if method matches
     *
     * @param  string $method
     *
     * @return bool
     */
    public function methodMatches(string $method) : bool
    {
        if (is_array($this->methods)) {
            return in_array($method, $this->methods, true);
        }

        if ($this->methods === '*') {
            return true;
        }

        return $method === $this->methods;
    }

    public function call(ServerRequestInterface $request) : ResponseInterface
    {
        return call_user_func($this->callback, $request);
    }
}