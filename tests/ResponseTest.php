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

        $this->assertEquals(200, $response->status());
        $this->assertEquals('hello', $response->body());

        $response->setStatus(201);
        $response->setBody('world');

        $this->assertEquals(201, $response->status());
        $this->assertEquals('world', $response->body());
    }

    public function testResponseError() : void
    {
        $response = new Response(404);

        $this->assertEquals(404, $response->status());
        $this->assertEquals('Not Found', $response->body());

        $this->assertEquals(new Response(410, 'custom message'), new Response(410, 'custom message'));
    }

    public function testToString() : void
    {
        $result = (string) new Response(200, 'hello world');

        $expected = <<<STR
            status: 200
            body: hello world

        STR;

        $this->assertEquals($expected, $result);
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
