<?php

declare(strict_types=1);

namespace Oct8pus\NanoRouter;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Route extends AbstractRoute
{
    protected readonly RouteType $type;

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
     * @param string $candidate
     *
     * @return bool
     *
     * @throws NanoRouterException
     */
    public function pathMatches(string $candidate) : bool
    {
        switch ($this->type) {
            case RouteType::Exact:
                return $candidate === $this->path;

            case RouteType::StartsWith:
                return str_starts_with($candidate, $this->path);

            case RouteType::Regex:
                return preg_match($this->path, $candidate) === 1;

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
}
