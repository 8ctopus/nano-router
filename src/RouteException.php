<?php

declare(strict_types=1);

namespace Oct8pus\NanoRouter;

use Exception;

class RouteException extends Exception
{
    private bool $debug;

    /**
     * Constructor
     *
     * @param string $message
     * @param int    $code
     * @param bool   $debug
     */
    public function __construct(string $message, int $code, bool $debug = false)
    {
        if ($code < 100 || $code >= 600) {
            throw new NanoRouterException("invalid status code - {$code}");
        }

        parent::__construct($message, $code);

        $this->debug = $debug;
    }

    public function debug() : bool
    {
        return $this->debug;
    }
}
