<?php

declare(strict_types=1);

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

        static::assertSame(200, $response->status());
        static::assertSame('hello', $response->body());

        $response->setStatus(201);
        $response->setBody('world');

        static::assertSame(201, $response->status());
        static::assertSame('world', $response->body());
    }

    public function testResponseError() : void
    {
        $response = new Response(404);

        static::assertSame(404, $response->status());
        static::assertSame('Not Found', $response->body());

        static::assertEquals(new Response(410, 'custom message'), new Response(410, 'custom message'));
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

        static::assertEquals(['location' => 'http://localhost'], $response->headers());

        $response = $response->removeHeader('location');

        static::assertEquals([], $response->headers());

        $response->setHeader('content-type', 'application/json');

        static::assertEquals(['content-type' => 'application/json'], $response->headers());
    }

    public function testSend() : void
    {
        static::expectOutputString(<<<OUTPUT
        header: content-type: application/json
        {"title": "hello world"}
        OUTPUT);

        $response = new MockResponse(200, '{"title": "hello world"}', ['content-type' => 'application/json']);
        $response->send();

    }

    public function testReSend() : void
    {
        static::expectException(NanoRouterException::class, 'Response already sent');

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