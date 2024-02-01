<?php

declare(strict_types=1);

namespace Oct8pus\NanoRouter;

use Oct8pus\NanoRouter\AbstractRoute;
use Psr\Http\Message\ResponseInterface;

class Middleware extends AbstractRoute
{
    private readonly MiddlewareType $type;

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
        $this->path = $pathRegex;
        $this->callback = $callback;
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
        return preg_match($this->path, $path) === 1;
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
