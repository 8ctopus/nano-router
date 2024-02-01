<?php

declare(strict_types=1);

namespace Oct8pus\NanoRouter;

use Psr\Http\Message\ResponseInterface;

class Middleware
{
    public readonly string $when;
    private readonly array|string $methods;
    private readonly string $regex;
    private $callback;

    /**
     * Constructor
     *
     * @param string    $when
     * @param string    $methods
     * @param string    $regex
     * @param callable  $callback
     *
     * @throws NanoRouterException
     */
    public function __construct(string $when, array|string $methods, string $regex, callable $callback)
    {
        if (!in_array($when, ['pre', 'post'], true)) {
            throw new NanoRouterException('invalid when clause');
        }

        if (!is_int(@preg_match($regex, ''))) {
            throw new NanoRouterException("invalid regex - {$regex}");
        }

        $this->when = $when;
        $this->methods = $methods;
        $this->regex = $regex;
        $this->callback = $callback;
    }

    /**
     * Check if matches
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
        return preg_match($this->regex, $path) === 1;
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

    /**
     * Call middleware
     *
     * @param $args
     *
     * @return ?ResponseInterface
     */
    public function call(...$args) : ?ResponseInterface
    {
        return call_user_func($this->callback, ...$args);
    }
}
