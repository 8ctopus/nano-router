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
        $result = (string) new Response(200, 'hello world');

        $expected = <<<'STR'
            status: 200
            body: hello world

        STR;

        static::assertSame($expected, $result);
    }

    public function testSend() : void
    {
        $this->expectOutputString('hello world');
        $response = new Response(200, 'hello world');
        $response->send();
    }

    public function testReSend() : void
    {
        $this->expectException(NanoRouterException::class, 'Response already sent');

        (new Response(200, 'hello world'))
            ->send()
            ->send();
    }
}
