<?php

declare(strict_types=1);

namespace Oct8pus\NanoRouter;

use Exception;

class RouteException extends Exception
{
    /**
     * Constructor
     *
     * @param string $message
     * @param int    $code
     */
    public function __construct(string $message, int $code)
    {
        if ($code < 100 || $code >= 600) {
            throw new NanoRouterException("invalid status code - {$code}");
        }

        parent::__construct($message, $code);
    }
}
