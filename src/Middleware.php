<?php

declare(strict_types=1);

namespace Oct8pus\NanoRouter;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Middleware extends AbstractRoute
{
    private readonly MiddlewareType $type;

    /**
     * Constructor
     *
     * @param MiddlewareType       $type
     * @param array<string>|string $method
     * @param string               $pathRegex
     * @param callable             $callback
     *
     * @throws NanoRouterException
     */
    public function __construct(MiddlewareType $type, array|string $method, string $pathRegex, callable $callback)
    {
        parent::__construct();

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
     * @param string $candidate
     *
     * @return bool
     */
    public function pathMatches(string $candidate) : bool
    {
        return preg_match($this->path, $candidate) === 1;
    }

    /**
     * Call middleware
     *
     * @param ServerRequestInterface $request
     *
     * @return ?ResponseInterface
     */
    public function callPre(ServerRequestInterface $request) : ?ResponseInterface
    {
        return call_user_func($this->callback, $request);
    }

    /**
     * Call post middleware
     *
     * @param ResponseInterface      $response
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function callPost(ResponseInterface $response, ServerRequestInterface $request) : ResponseInterface
    {
        return call_user_func($this->callback, $response, $request);
    }

    public function type() : MiddlewareType
    {
        return $this->type;
    }
}
