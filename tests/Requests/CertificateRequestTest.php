<?php

declare(strict_types=1);

namespace Tests\Requests;

use PHPUnit\Framework\TestCase;
use Sevaske\ZatcaApi\Requests\CertificateRequest;

final class CertificateRequestTest extends TestCase
{
    /**
     * We define a small concrete class for testing
     */
    private function createRequest(string $csr, string $otp): CertificateRequest
    {
        return new class($csr, $otp) extends CertificateRequest
        {
            public function uri(): string
            {
                return '/some-url';
            }
        };
    }

    public function test_request_uses_correct_uri(): void
    {
        $req = $this->createRequest('CSR-DATA', '000111');

        $this->assertSame('/some-url', $req->uri());
    }

    public function test_body_contains_base64_encoded_csr(): void
    {
        $csr = 'my-csr-value';
        $req = $this->createRequest($csr, '123456');

        $options = $req->options();

        $this->assertArrayHasKey('body', $options);
        $this->assertArrayHasKey('csr', $options['body']);
        $this->assertSame(base64_encode($csr), $options['body']['csr']);
    }

    public function test_otp_header_is_set(): void
    {
        $otp = '999777';
        $req = $this->createRequest('csr', $otp);

        $options = $req->options();

        $this->assertArrayHasKey('headers', $options);
        $this->assertArrayHasKey('OTP', $options['headers']);
        $this->assertSame($otp, $options['headers']['OTP']);
    }

    public function test_default_headers_are_kept_and_merged(): void
    {
        $req = $this->createRequest('csr', 'otp123');
        $options = $req->options();

        // default headers from Request class
        $this->assertSame('application/json', $options['headers']['Accept']);
        $this->assertSame('application/json', $options['headers']['Content-Type']);
        $this->assertSame('en', $options['headers']['Accept-Language']);
        $this->assertSame('V2', $options['headers']['Accept-Version']);

        // certificate-specific header
        $this->assertSame('otp123', $options['headers']['OTP']);
    }

    public function test_method_is_post(): void
    {
        $req = $this->createRequest('csr', 'otp');
        $this->assertSame('POST', $req->method());
    }

    public function test_options_contain_body_and_headers(): void
    {
        $req = $this->createRequest('csr', 'otp567');
        $options = $req->options();

        $this->assertArrayHasKey('body', $options);
        $this->assertArrayHasKey('headers', $options);

        $this->assertSame(base64_encode('csr'), $options['body']['csr']);
        $this->assertSame('otp567', $options['headers']['OTP']);
    }
}
