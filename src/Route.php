<?php

declare(strict_types=1);

namespace Oct8pus\NanoRouter;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Route
{
    private readonly RouteType $type;
    private readonly array $methods;
    private readonly string $path;
    private $callback;

    /**
     * Constructor
     *
     * @param RouteType    $type
     * @param array|string $method
     * @param string       $path
     * @param callable     $callback
     *
     * @throws NanoRouterException
     */
    public function __construct(RouteType $type, array|string $method, string $path, callable $callback)
    {
        if ($type === RouteType::Regex && !is_int(@preg_match($path, ''))) {
            throw new NanoRouterException("invalid regex - {$path}");
        }

        $this->type = $type;
        $this->methods = !is_array($method) ? [$method] : $method;
        $this->path = $path;
        $this->callback = $callback;
    }

    /**
     * Check if route matches
     *
     * @param string $method
     * @param string $path
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
     * @param string $path
     *
     * @return bool
     *
     * @throws NanoRouterException
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
                // @codeCoverageIgnoreStart
                throw new NanoRouterException("Unknown route type - {$this->type}");
                // @codeCoverageIgnoreEnd
        }
    }

    /**
     * Check if method matches
     *
     * @param string $method
     *
     * @return bool
     */
    public function methodMatches(string $method) : bool
    {
        if ($this->methods[0] === '*') {
            return true;
        }

        return in_array($method, $this->methods, true);
    }

    /**
     * Call route
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function call(ServerRequestInterface $request) : ResponseInterface
    {
        return call_user_func($this->callback, $request);
    }
}
