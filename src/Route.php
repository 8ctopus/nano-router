<?php

declare(strict_types=1);

namespace Oct8pus\NanoRouter;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Route extends AbstractRoute
{
    private readonly RouteType $type;

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
        $this->pathes[] = $path;
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
        switch ($this->type) {
            case RouteType::Exact:
                return in_array($path, $this->pathes, true);

            case RouteType::StartsWith:
                foreach ($this->pathes as $item) {
                    if (str_starts_with($path, $item)) {
                        return true;
                    }
                }

                return false;

            case RouteType::Regex:
                foreach ($this->pathes as $item) {
                    if (preg_match($item, $path) === 1) {
                        return true;
                    }
                }

                return false;

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
        $this->pathes[] = $path;
        return $this;
    }
}
