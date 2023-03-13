<?php

declare(strict_types=1);

namespace Oct8pus\NanoRouter;

class Response
{
    private int $code;
    private string $body;
    private bool $sent;

    public function __construct(int $code = 200, string $body = '')
    {
        $this->code = $code;
        $this->body = $body;
        $this->sent = false;
    }

    public function send() : void
    {
        if ($this->sent) {
            throw new NanoRouterException('Response already sent');
        }

        $this->sent = true;

        http_response_code($this->code);
        echo $this->body;
    }

    public function status() : int
    {
        return $this->code;
    }

    public function setStatus(int $code) : void
    {
        $this->code = $code;
    }

    public function body() : string
    {
        return $this->body;
    }

    public function setBody(string $body) : void
    {
        $this->body = $body;
    }

    public function __toString() : string
    {
        return <<<STR
            status: {$this->code}
            body: {$this->body}

        STR;
    }
}
