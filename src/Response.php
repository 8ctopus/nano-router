<?php

declare(strict_types=1);

namespace Oct8pus\NanoRouter;

class Response
{
    private array $messages;

    private int $status;
    private string $body;

    private bool $sent;

    public function __construct(int $status = 200, string $body = '')
    {
        $this->messages = [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Timeout',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Payload Too Large',
            414 => 'URI Too Long',
            415 => 'Unsupported Media Type',
            416 => 'Range Not Satisfiable',
            417 => 'Expectation Failed',
            418 => 'I\'m a teapot (RFC 2324, RFC 7168)',
            421 => 'Misdirected Request',
            422 => 'Unprocessable Entity',
            423 => 'Locked (WebDAV; RFC 4918)',
            424 => 'Failed Dependency (WebDAV; RFC 4918)',
            425 => 'Too Early (RFC 8470)',
            426 => 'Upgrade Required',
            428 => 'Precondition Required (RFC 6585)',
            429 => 'Too Many Requests (RFC 6585)',
            431 => 'Request Header Fields Too Large (RFC 6585)',
            451 => 'Unavailable For Legal Reasons (RFC 7725)',
        ];

        $this->setBody($body);
        $this->setStatus($status);
        $this->sent = false;

        if (empty($body) && $status >= 400) {
            $this->body = $this->messages[$status];
        }
    }

    public function send() : self
    {
        if ($this->sent) {
            throw new NanoRouterException('Response already sent');
        }

        $this->sent = true;

        http_response_code($this->status);
        echo $this->body;

        return $this;
    }

    public function status() : int
    {
        return $this->status;
    }

    public function setStatus(int $status) : void
    {
        $this->status = $status;

        if (empty($this->body) && $status >= 400) {
            $this->body = $this->messages[$status];
        }
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
            status: {$this->status}
            body: {$this->body}

        STR;
    }
}
