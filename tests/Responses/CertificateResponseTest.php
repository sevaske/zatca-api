<?php

declare(strict_types=1);

namespace Tests\Responses;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Sevaske\ZatcaApi\Responses\CertificateResponse;

final class CertificateResponseTest extends TestCase
{
    private function createResponse(array $data = []): CertificateResponse
    {
        $body = json_encode($data);

        $stream = $this->createMock(StreamInterface::class);
        $stream->method('getContents')->willReturn($body);
        $stream->method('isSeekable')->willReturn(false);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getBody')->willReturn($stream);

        return new class($response) extends CertificateResponse {};
    }

    public function test_success_returns_true_for_issued(): void
    {
        $response = $this->createResponse(['dispositionMessage' => 'ISSUED']);
        $this->assertTrue($response->success());
    }

    public function test_success_returns_false_for_non_issued(): void
    {
        $response = $this->createResponse(['dispositionMessage' => 'REJECTED']);
        $this->assertFalse($response->success());

        $response = $this->createResponse([]);
        $this->assertFalse($response->success());
    }

    public function test_request_id_returns_int_or_null(): void
    {
        $response = $this->createResponse(['requestID' => '123']);
        $this->assertSame(123, $response->requestId());

        $response = $this->createResponse([]);
        $this->assertNull($response->requestId());
    }

    public function test_secret_returns_value_or_null(): void
    {
        $response = $this->createResponse(['secret' => 'abc123']);
        $this->assertSame('abc123', $response->secret());

        $response = $this->createResponse([]);
        $this->assertNull($response->secret());
    }

    public function test_binary_security_token_returns_value_or_null(): void
    {
        $response = $this->createResponse(['binarySecurityToken' => base64_encode('cert')]);
        $this->assertSame(base64_encode('cert'), $response->binarySecurityToken());

        $response = $this->createResponse([]);
        $this->assertNull($response->binarySecurityToken());
    }

    public function test_certificate_decodes_binary_security_token(): void
    {
        $token = base64_encode('my-cert');
        $response = $this->createResponse(['binarySecurityToken' => $token]);
        $this->assertSame('my-cert', $response->certificate());
    }

    public function test_errors_returns_array(): void
    {
        $response = $this->createResponse(['errors' => ['error1', 'error2']]);
        $this->assertSame(['error1', 'error2'], $response->errors());

        $response = $this->createResponse([]);
        $this->assertSame([], $response->errors());
    }

    public function test_has_errors_returns_true_or_false(): void
    {
        $response = $this->createResponse(['errors' => ['error']]);
        $this->assertTrue($response->hasErrors());

        $response = $this->createResponse([]);
        $this->assertFalse($response->hasErrors());
    }
}
