<?php

declare(strict_types=1);

namespace Tests\Traits;

use PHPUnit\Framework\TestCase;
use Sevaske\ZatcaApi\Traits\Http;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Utils;

final class HttpTraitTest extends TestCase
{
    private function createObjectWithTrait(): object
    {
        return new class {
            use Http;

            public function setDependencies(
                ClientInterface $client,
                RequestFactoryInterface $requestFactory,
                StreamFactoryInterface $streamFactory
            ): void {
                $this->client = $client;
                $this->requestFactory = $requestFactory;
                $this->streamFactory = $streamFactory;
            }

            // Expose prepareRequest for testing
            public function preparePublic(string $method, string $uri, array $options = [])
            {
                return $this->prepareRequest($method, $uri, $options);
            }
        };
    }

    public function test_get_client_returns_client(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $obj = $this->createObjectWithTrait();
        $obj->setDependencies($client, $this->createMock(RequestFactoryInterface::class), $this->createMock(StreamFactoryInterface::class));

        $this->assertSame($client, $obj->getClient());
    }

    public function test_prepare_request_with_query_headers_and_json_body(): void
    {
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $requestFactory->method('createRequest')->willReturn(new Request('POST', 'https://example.com'));

        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $streamFactory->method('createStream')->willReturnCallback(fn($body) => Utils::streamFor($body));

        $obj = $this->createObjectWithTrait();
        $obj->setDependencies($this->createMock(ClientInterface::class), $requestFactory, $streamFactory);

        $options = [
            'query' => ['a' => 1],
            'headers' => ['Content-Type' => 'application/json', 'X-Test' => 'value'],
            'body' => ['key' => 'value']
        ];

        $request = $obj->preparePublic('POST', 'https://example.com', $options);

        $this->assertInstanceOf(Request::class, $request);
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('application/json', $request->getHeaderLine('Content-Type'));
        $this->assertSame('value', $request->getHeaderLine('X-Test'));
        $this->assertSame(json_encode(['key' => 'value']), (string) $request->getBody());
        $this->assertSame('a=1', $request->getUri()->getQuery());
    }

    public function test_prepare_request_body_encoded_as_urlencoded_if_content_type_not_json(): void
    {
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $requestFactory->method('createRequest')->willReturn(new Request('POST', 'https://example.com'));

        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $streamFactory->method('createStream')->willReturnCallback(fn($body) => Utils::streamFor($body));

        $obj = $this->createObjectWithTrait();
        $obj->setDependencies($this->createMock(ClientInterface::class), $requestFactory, $streamFactory);

        $options = [
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'body' => ['key' => 'value']
        ];

        $request = $obj->preparePublic('POST', 'https://example.com', $options);

        $this->assertSame('key=value', (string) $request->getBody());
        $this->assertSame('application/x-www-form-urlencoded', $request->getHeaderLine('Content-Type'));
    }

    public function test_prepare_request_throws_zatca_request_exception_on_error(): void
    {
        $requestFactory = $this->createMock(RequestFactoryInterface::class);

        // When createRequest is called, throw a standard Exception
        $requestFactory->method('createRequest')->willReturnCallback(function() {
            throw new \Exception('fail');
        });

        $obj = $this->createObjectWithTrait();
        $obj->setDependencies(
            $this->createMock(ClientInterface::class),
            $requestFactory,
            $this->createMock(StreamFactoryInterface::class)
        );

        $this->expectException(\Sevaske\ZatcaApi\Exceptions\ZatcaRequestException::class);

        // Call the method that wraps exceptions
        $obj->preparePublic('GET', 'https://example.com');
    }
}