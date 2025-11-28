<?php

declare(strict_types=1);

namespace Tests\Responses;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Sevaske\ZatcaApi\Responses\ClearanceInvoiceResponse;

final class ClearanceInvoiceResponseTest extends TestCase
{
    /**
     * Helper to create a mock ResponseInterface with given attributes as JSON body.
     */
    protected function makeResponse(array $attributes): ClearanceInvoiceResponse
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

        return new ClearanceInvoiceResponse($response);
    }

    public function test_success_cleared_invoice(): void
    {
        $response = $this->makeResponse([
            'clearanceStatus' => 'CLEARED',
            'clearedInvoice' => 'INV-001',
        ]);

        $this->assertTrue($response->success());
        $this->assertEquals('CLEARED', $response->status());
        $this->assertEquals('INV-001', $response->clearedInvoice());
    }

    public function test_unsuccessful_invoice(): void
    {
        $response = $this->makeResponse([
            'clearanceStatus' => 'PENDING',
            'clearedInvoice' => null,
        ]);

        $this->assertFalse($response->success());
        $this->assertEquals('PENDING', $response->status());
        $this->assertNull($response->clearedInvoice());
    }

    public function test_missing_attributes(): void
    {
        $response = $this->makeResponse([]);

        $this->assertFalse($response->success());
        $this->assertNull($response->status());
        $this->assertNull($response->clearedInvoice());
    }
}
