<?php

declare(strict_types=1);

namespace Tests\Responses;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Sevaske\ZatcaApi\Responses\ReportingInvoiceResponse;

final class ReportingInvoiceResponseTest extends TestCase
{
    /**
     * Helper to create a mock ResponseInterface with given attributes as JSON body.
     */
    protected function makeResponse(array $attributes): ReportingInvoiceResponse
    {
        $json = json_encode($attributes, JSON_THROW_ON_ERROR);

        // Mock the stream to return the JSON content
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('getContents')->willReturn($json);
        $stream->method('isSeekable')->willReturn(true);
        $stream->method('rewind');

        // Mock the ResponseInterface
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getBody')->willReturn($stream);
        $response->method('getStatusCode')->willReturn(200);

        return new ReportingInvoiceResponse($response);
    }

    public function test_success_reported_invoice(): void
    {
        $response = $this->makeResponse([
            'reportingStatus' => 'REPORTED',
        ]);

        $this->assertTrue($response->success());
        $this->assertEquals('REPORTED', $response->status());
    }

    public function test_unsuccessful_invoice(): void
    {
        $response = $this->makeResponse([
            'reportingStatus' => 'PENDING',
        ]);

        $this->assertFalse($response->success());
        $this->assertEquals('PENDING', $response->status());
    }

    public function test_missing_attributes(): void
    {
        $response = $this->makeResponse([]);

        $this->assertFalse($response->success());
        $this->assertNull($response->status());
    }
}
