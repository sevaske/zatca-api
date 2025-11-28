<?php

declare(strict_types=1);

namespace Tests\Responses;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Sevaske\ZatcaApi\Responses\ClearanceInvoiceResponse;

final class ClearanceInvoiceResponseTest extends TestCase
{
    private function createResponse(array $data = []): ClearanceInvoiceResponse
    {
        $body = json_encode($data);

        $stream = $this->createMock(StreamInterface::class);
        $stream->method('getContents')->willReturn($body);
        $stream->method('isSeekable')->willReturn(false);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getBody')->willReturn($stream);

        return new class($response) extends ClearanceInvoiceResponse {};
    }

    public function test_success_returns_true_for_cleared(): void
    {
        $response = $this->createResponse(['clearanceStatus' => 'CLEARED']);
        $this->assertTrue($response->success());
    }

    public function test_success_returns_false_for_non_cleared(): void
    {
        $response = $this->createResponse(['clearanceStatus' => 'PENDING']);
        $this->assertFalse($response->success());

        $response = $this->createResponse([]);
        $this->assertFalse($response->success());
    }

    public function test_status_returns_value_or_null(): void
    {
        $response = $this->createResponse(['clearanceStatus' => 'CLEARED']);
        $this->assertSame('CLEARED', $response->status());

        $response = $this->createResponse([]);
        $this->assertNull($response->status());
    }

    public function test_cleared_invoice_returns_value_or_null(): void
    {
        $response = $this->createResponse(['clearedInvoice' => 'invoice123']);
        $this->assertSame('invoice123', $response->clearedInvoice());

        $response = $this->createResponse([]);
        $this->assertNull($response->clearedInvoice());
    }
}
