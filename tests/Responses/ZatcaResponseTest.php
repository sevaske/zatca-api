<?php

declare(strict_types=1);

namespace Tests\Responses;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Sevaske\ZatcaApi\Exceptions\ZatcaResponseException;
use Sevaske\ZatcaApi\Responses\ZatcaResponse;

final class ZatcaResponseTest extends TestCase
{
    private function createMockResponse(array $data = [], int $status = 200): ResponseInterface
    {
        $json = json_encode($data);

        $stream = $this->createMock(StreamInterface::class);
        $stream->method('getContents')->willReturn($json);
        $stream->method('isSeekable')->willReturn(false);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getBody')->willReturn($stream);
        $response->method('getStatusCode')->willReturn($status);

        return $response;
    }

    /**
     * Creates a minimal concrete implementation of ZatcaResponse.
     */
    private function makeInstance(ResponseInterface $response): ZatcaResponse
    {
        return new class($response) extends ZatcaResponse {
            // no properties, no overrides
        };
    }

    public function test_constructor_parses_attributes(): void
    {
        $data = ['foo' => 'bar', 'baz' => 123];
        $response = $this->createMockResponse($data);

        $instance = $this->makeInstance($response);

        $this->assertSame('bar', $instance->foo);
        $this->assertSame(123, $instance->baz);
    }

    public function test_raw_returns_response(): void
    {
        $response = $this->createMockResponse();
        $instance = $this->makeInstance($response);

        $this->assertSame($response, $instance->raw());
    }

    public function test_parse_returns_array_from_json(): void
    {
        $data = ['key' => 'value'];
        $response = $this->createMockResponse($data);

        $parsed = ZatcaResponse::parse($response);
        $this->assertSame($data, $parsed);
    }

    public function test_parse_throws_exception_on_invalid_json(): void
    {
        $invalidJson = '{invalid json}';

        $stream = $this->createMock(StreamInterface::class);
        $stream->method('getContents')->willReturn($invalidJson);
        $stream->method('isSeekable')->willReturn(false);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getBody')->willReturn($stream);
        $response->method('getStatusCode')->willReturn(500);

        $this->expectException(ZatcaResponseException::class);

        ZatcaResponse::parse($response);
    }
}