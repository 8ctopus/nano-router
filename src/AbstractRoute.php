<?php

declare(strict_types=1);

namespace Oct8pus\NanoRouter;

abstract class AbstractRoute
{
    /**
     * @var array<string>
     */
    protected array $methods;
    protected string $path;

    /**
     * @var callable
     */
    protected $callback;

    public function __construct()
    {
        $this->methods = [];
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
     * @param string $candidate
     *
     * @return bool
     */
    abstract public function pathMatches(string $candidate) : bool;

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
}
