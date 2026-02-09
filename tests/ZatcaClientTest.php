<?php

declare(strict_types=1);

namespace Tests;

use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Sevaske\ZatcaApi\ZatcaAuth;
use Sevaske\ZatcaApi\ZatcaClient;
use Sevaske\ZatcaApi\Exceptions\ZatcaRequestException;

final class ZatcaClientTest extends TestCase
{
    public function test_withEnvironment_returns_clone_with_new_environment_and_resets_token(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);

        $clientObj = new ZatcaClient($client, $requestFactory, $streamFactory, 'sandbox');

        // set an auth token on the original
        $clientObj->setAuthToken(new ZatcaAuth('cert', 'secret'));

        // create a clone with production environment
        $prod = $clientObj->withEnvironment('production');

        $this->assertSame('production', (string) $prod->environment());
        $this->assertSame('sandbox', (string) $clientObj->environment());

        // the cloned instance should have reset auth token — attempting an authorized request must fail
        $this->expectException(ZatcaRequestException::class);

        // call a method that requires auth; buildRequest will throw due to missing token
        $prod->reportingInvoice('invoice', 'hash', 'uuid');
    }

    public function test_sendRequest_exception_context_masks_authorization_and_truncates_body(): void
    {
        $client = $this->createMock(ClientInterface::class);

        // prepare an exception implementing ClientExceptionInterface
        $clientEx = new class('client fail') extends \RuntimeException implements ClientExceptionInterface {};

        $client->method('sendRequest')->willThrowException($clientEx);

        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        // use a real PSR-7 Request when createRequest is called
        $requestFactory->method('createRequest')->willReturnCallback(function ($method, $uri) {
            return new \GuzzleHttp\Psr7\Request($method, $uri);
        });

        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $streamFactory->method('createStream')->willReturnCallback(fn ($body) => Utils::streamFor($body));

        $clientObj = new ZatcaClient($client, $requestFactory, $streamFactory, 'sandbox');

        // attach a valid auth token so Authorization header will be present
        $clientObj->setAuthToken(new ZatcaAuth('certificate', 'secret'));

        // make a large invoice so body becomes larger than truncation threshold
        $large = str_repeat('A', 2000);

        try {
            $clientObj->reportingInvoice($large, 'hash', 'uuid');
            $this->fail('Expected ZatcaRequestException not thrown');
        } catch (ZatcaRequestException $e) {
            $ctx = $e->context();

            $this->assertArrayHasKey('headers', $ctx);
            $this->assertArrayHasKey('Authorization', $ctx['headers']);
            $this->assertSame(['[REDACTED]'], $ctx['headers']['Authorization']);

            $this->assertArrayHasKey('body', $ctx);
            $this->assertIsString($ctx['body']);
            $this->assertStringContainsString('... (truncated)', $ctx['body']);
        }
    }
}
