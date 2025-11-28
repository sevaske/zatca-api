<?php

declare(strict_types=1);

namespace Tests\Requests;

use PHPUnit\Framework\TestCase;
use Sevaske\ZatcaApi\Interfaces\RequiresAuthTokenInterface;
use Sevaske\ZatcaApi\Requests\InvoiceRequest;

final class InvoiceRequestTest extends TestCase
{
    /**
     * Create a concrete instance of InvoiceRequest for testing.
     */
    private function createRequest(string $invoice, string $hash, string $uuid): InvoiceRequest
    {
        return new class($invoice, $hash, $uuid) extends InvoiceRequest
        {
            public function uri(): string
            {
                return '/some-url';
            }
        };
    }

    public function test_uri_is_correct(): void
    {
        $req = $this->createRequest('xml', 'hash', 'uuid');
        $this->assertSame('/some-url', $req->uri());
    }

    public function test_invoice_is_base64_encoded_in_body(): void
    {
        $invoice = 'xml';
        $req = $this->createRequest($invoice, 'hash', 'uuid');

        $options = $req->options();

        $this->assertArrayHasKey('body', $options);
        $this->assertSame(base64_encode($invoice), $options['body']['invoice']);
    }

    public function test_hash_and_uuid_are_set_correctly(): void
    {
        $hash = 'HASH-XYZ';
        $uuid = 'UUID-001';

        $req = $this->createRequest('inv', $hash, $uuid);

        $options = $req->options();

        $this->assertSame($hash, $options['body']['hash']);
        $this->assertSame($uuid, $options['body']['uuid']);
    }

    public function test_default_headers_are_present(): void
    {
        $req = $this->createRequest('xml', 'hash', 'uui');
        $headers = $req->options()['headers'];

        $this->assertSame('application/json', $headers['Accept']);
        $this->assertSame('application/json', $headers['Content-Type']);
        $this->assertSame('en', $headers['Accept-Language']);
        $this->assertSame('V2', $headers['Accept-Version']);
    }

    public function test_method_is_post(): void
    {
        $req = $this->createRequest('xml', 'hash', 'uui');
        $this->assertSame('POST', $req->method());
    }

    public function test_options_structure_is_correct(): void
    {
        $req = $this->createRequest('xml', 'hash', 'uui');
        $options = $req->options();

        $this->assertArrayHasKey('body', $options);
        $this->assertArrayHasKey('headers', $options);

        $this->assertArrayHasKey('invoice', $options['body']);
        $this->assertArrayHasKey('hash', $options['body']);
        $this->assertArrayHasKey('uuid', $options['body']);
    }

    public function test_implements_requires_auth_token_interface(): void
    {
        $req = $this->createRequest('xml', 'hash', 'uui');
        $this->assertInstanceOf(RequiresAuthTokenInterface::class, $req);
    }
}
