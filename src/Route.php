<?php

declare(strict_types=1);

namespace Oct8pus\NanoRouter;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Route extends AbstractRoute
{
    private readonly RouteType $type;
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
        parent::__construct();

        if ($type === RouteType::Regex && !is_int(@preg_match($path, ''))) {
            throw new NanoRouterException("invalid regex - {$path}");
        }

        $this->type = $type;
        $this->methods = !is_array($method) ? [$method] : $method;
        $this->path = $path;
        $this->callback = $callback;
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
        if ($this->internalPathMatches($this->path, $path)) {
            return true;
        }

        if (isset($this->alias) && $this->internalPathMatches($this->alias, $path)) {
            return true;
        }

        return false;
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

    /**
     * Check if path matches
     *
     * @param  string $path
     * @param  string $candidate
     *
     * @return bool
     */
    private function internalPathMatches(string $path, string $candidate) : bool
    {
        switch ($this->type) {
            case RouteType::Exact:
                return $path === $candidate;

            case RouteType::StartsWith:
                return str_starts_with($candidate, $path);

            case RouteType::Regex:
                return preg_match($path, $candidate) === 1;

            default:
                // @codeCoverageIgnoreStart
                throw new NanoRouterException("Unknown route type - {$this->type}");
                // @codeCoverageIgnoreEnd
        }
    }
}
