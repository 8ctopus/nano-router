<?php

declare(strict_types=1);

namespace Oct8pus\NanoRouter;

use Psr\Http\Message\ResponseInterface;

class Middleware
{
    private readonly MiddlewareType $type;
    private readonly array $methods;
    private readonly string $regex;
    private $callback;

    /**
     * Constructor
     *
     * @param MiddlewareType $type
     * @param array|string   $method
     * @param string         $pathRegex
     * @param callable       $callback
     *
     * @throws NanoRouterException
     */
    public function __construct(MiddlewareType $type, array|string $method, string $pathRegex, callable $callback)
    {
        if (!is_int(@preg_match($pathRegex, ''))) {
            throw new NanoRouterException("invalid regex - {$pathRegex}");
        }

        $this->type = $type;
        $this->methods = !is_array($method) ? [$method] : $method;
        $this->regex = $pathRegex;
        $this->callback = $callback;
    }

    /**
     * Check if matches
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
     */
    public function pathMatches(string $path) : bool
    {
        return preg_match($this->regex, $path) === 1;
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
     * Call middleware
     *
     * @param ...$args - for pre only ServerRequestInterface - for post Response and ServerRequestInterface
     *
     * @return ?ResponseInterface
     */
    public function call(...$args) : ?ResponseInterface
    {
        return call_user_func($this->callback, ...$args);
    }

    public function type() : MiddlewareType
    {
        return $this->type;
    }
}
