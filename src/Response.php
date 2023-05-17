<?php

declare(strict_types=1);

namespace Oct8pus\NanoRouter;

use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class Response implements ResponseInterface
{
    private static array $reasons = [
        // informational
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        103 => 'Early Hints',
        // successful
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        208 => 'Already Reported',
        226 => 'IM Used',
        // redirection
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        // client errors
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
        418 => 'I\'m a teapot',
        421 => 'Misdirected Request',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Too Early',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        451 => 'Unavailable For Legal Reasons',
        // server errors
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        510 => 'Not Extended',
        511 => 'Network Authentication Required',
    ];

    private int $status;
    private string $reasonPhrase;

    private array $headers;
    private string|StreamInterface $body;

    private bool $sent;

    /**
     * Constructor
     *
     * @param int    $status
     * @param string $body
     * @param array  $headers
     */
    public function __construct(int $status = 200, string $body = '', array $headers = [])
    {
        $this->sent = false;

        $this->withStatus($status);
        $this->withBodyText($body);

        $this->headers = $headers;
    }

    /**
     * Send response
     *
     * @return self
     *
     * @throws NanoRouterException
     */
    public function send() : self
    {
        if ($this->sent) {
            throw new NanoRouterException('Response already sent');
        }

        $this->sent = true;

        foreach ($this->headers as $name => $value) {
            $this->header("{$name}: {$value}");
        }

        http_response_code($this->status);

        echo $this->body;

        return $this;
    }

    public function getStatusCode() : int
    {
        return $this->status;
    }

    public function withStatus(int $status, string $reasonPhrase = '') : self
    {
        if ($status < 100 || $status > 599) {
            throw new Exception('status must be in [100,600[ range');
        }

        $this->status = $status;

        if ($reasonPhrase === '') {
            $reasonPhrase = static::$reasons[$status];
        }

        $this->reasonPhrase = $reasonPhrase;

        return $this;
    }

    public function getHeaders() : array
    {
        return $this->headers;
    }

    public function hasHeader(string $name) : bool
    {
        return array_key_exists($name, $this->headers);
    }

    public function getHeader(string $name) : array
    {
        return $this->headers[$name];
    }

    public function getHeaderLine(string $name) : string
    {
        throw new Exception('not implemented');
    }

    public function withHeader(string $name, $value) : self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function withAddedHeader(string $name, $value) : self
    {
        throw new Exception('not implemented');
    }

    public function withoutHeader(string $name) : self
    {
        unset($this->headers[$name]);
        return $this;
    }

    public function getBody() : StreamInterface
    {
        throw new Exception('not implemented');
    }

    public function getBodyText() : string
    {
        return $this->body;
    }

    public function withBody(StreamInterface $body) : self
    {
        throw new Exception('not implemented');
    }

    public function withBodyText(string $body) : self
    {
        $this->body = $body;
        return $this;
    }

    public function getReasonPhrase() : string
    {
        return $this->reasonPhrase;
    }

    public function getProtocolVersion(): string
    {
        throw new Exception('not implemented');
    }

    public function withProtocolVersion(string $version) : self
    {
        throw new Exception('not implemented');
    }

    public function __toString() : string
    {
        $headers = "";

        foreach ($this->headers as $name => $value) {
            $headers .= "    {$name}: {$value}\n";
        }

        return <<<STR
        status: {$this->status}
        headers:
        {$headers}body:
            {$this->body}

        STR;
    }

    protected function header(string $header) : self
    {
        // @codeCoverageIgnoreStart
        header($header);
        return $this;
        // @codeCoverageIgnoreEnd
    }
}
