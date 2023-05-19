<?php

declare(strict_types=1);

namespace Tests;

use Oct8pus\NanoRouter\NanoRouterException;
use Oct8pus\NanoRouter\Response;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @covers \Oct8pus\NanoRouter\Response
 */
final class ResponseTest extends TestCase
{
    public function testResponse() : void
    {
        $response = new Response(200, 'hello');

        static::assertSame(200, $response->getStatusCode());
        static::assertSame('hello', $response->getBodyText());

        $response->withStatus(201);
        $response->withBodyText('world');

        static::assertSame(201, $response->getStatusCode());
        static::assertSame('world', $response->getBodyText());
    }

    public function testResponseError() : void
    {
        $response = new Response(404);

        static::assertSame(404, $response->getStatusCode());
        static::assertSame('', $response->getBodyText());
        static::assertSame('Not Found', $response->getReasonPhrase());

        $response = new Response(410, 'custom message');

        static::assertSame(410, $response->getStatusCode());
        static::assertSame('custom message', $response->getBodyText());
    }

    public function testToString() : void
    {
        $result = (string) new Response(200, '{"title": "hello world"}', ['content-type' => 'application/json']);

        $expected = <<<'STR'
        status: 200
        headers:
            content-type: application/json
        body:
            {"title": "hello world"}

        STR;

        static::assertSame($expected, $result);
    }

    public function testHeaders() : void
    {
        $response = new Response(301, '', ['location' => 'http://localhost']);

        static::assertSame(['location' => 'http://localhost'], $response->getHeaders());

        $response = $response->withoutHeader('location');

        static::assertSame([], $response->getHeaders());

        $response->withHeader('content-type', 'application/json');

        static::assertSame(['content-type' => 'application/json'], $response->getHeaders());
    }

    public function testSend() : void
    {
        static::expectOutputString(<<<'OUTPUT'
        header: content-type: application/json
        {"title": "hello world"}
        OUTPUT);

        $response = new MockResponse(200, '{"title": "hello world"}', ['content-type' => 'application/json']);
        $response->send();
    }

    public function testReSend() : void
    {
        static::expectException(NanoRouterException::class, 'Response already sent');

        static::expectOutputString('hello world');

        (new Response(200, 'hello world', []))
            ->send()
            ->send();
    }
}

class MockResponse extends Response
{
    protected function header(string $header) : self
    {
        echo "header: {$header}\n";
        return $this;
    }
}
