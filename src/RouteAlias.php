<?php

declare(strict_types=1);

namespace Oct8pus\NanoRouter;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class RouteAlias extends Route
{
    private array $aliases;

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
        $this->aliases = [];

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

        foreach ($this->aliases as $alias) {
            switch ($this->type) {
                case RouteType::Exact:
                    if ($candidate === $alias) {
                        return true;
                    }

                    break;

                case RouteType::StartsWith:
                    if (str_starts_with($candidate, $alias)) {
                        return true;
                    }

                    break;

                case RouteType::Regex:
                    if (preg_match($alias, $candidate) === 1) {
                        return true;
                    }

                    break;

                default:
                    // @codeCoverageIgnoreStart
                    throw new NanoRouterException("Unknown route type - {$this->type->value}");
                    // @codeCoverageIgnoreEnd
            }
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
     * @param string $path
     *
     * @return self
     */
    public function addAlias(string $path) : self
    {
        $this->aliases[] = $path;
        return $this;
    }
}
