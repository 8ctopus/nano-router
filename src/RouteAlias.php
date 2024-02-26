<?php

declare(strict_types=1);

namespace Oct8pus\NanoRouter;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class RouteAlias extends Route
{
    private readonly string $alias;

    /**
     * Constructor
     *
     * @param RouteType            $type
     * @param array<string>|string $method
     * @param string               $path
     * @param callable             $callback
     *
     * @throws NanoRouterException
     */
    public function __construct(RouteType $type, array|string $method, string $path, callable $callback)
    {
        parent::__construct($type, $method, $path, $callback);
    }

    /**
     * Check if path matches
     *
     * @param string $candidate
     *
     * @return bool
     *
     * @throws NanoRouterException
     */
    public function pathMatches(string $candidate) : bool
    {
        if (parent::pathMatches($candidate)) {
            return true;
        }

        switch ($this->type) {
            case RouteType::Exact:
                return $candidate === $this->alias;

            case RouteType::StartsWith:
                return str_starts_with($candidate, $this->alias);

            case RouteType::Regex:
                return preg_match($this->alias, $candidate) === 1;

            default:
                // @codeCoverageIgnoreStart
                throw new NanoRouterException("Unknown route type - {$this->type}");
                // @codeCoverageIgnoreEnd
        }
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

    /**
     * Add route alias
     *
     * @param  string $path
     *
     * @return self
     */
    public function addAlias(string $path) : self
    {
        $this->alias = $path;
        return $this;
    }
}
