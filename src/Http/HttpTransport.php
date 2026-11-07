<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Http;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Sevaske\ZatcaApi\Credentials\Credentials;
use Sevaske\ZatcaApi\Exception\InvalidArgumentException;
use Sevaske\ZatcaApi\Exception\TransportException;

/**
 * Low-level JSON-over-HTTP transport for the ZATCA e-invoicing API.
 *
 * It never throws for HTTP error statuses: the API classes decide which statuses are results
 * (e.g. a rejected invoice) and which are errors.
 *
 * @internal not covered by the backward compatibility promise
 */
final class HttpTransport
{
    /**
     * Only API version supported by ZATCA; any other value is answered with HTTP 406.
     */
    private const API_VERSION = 'V2';

    private const SENSITIVE_HEADERS = ['Authorization', 'OTP'];

    private ClientInterface $httpClient;

    private RequestFactoryInterface $requestFactory;

    private StreamFactoryInterface $streamFactory;

    private string $baseUri;

    private string $language;

    private string $userAgent;

    public function __construct(
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        string $baseUri,
        string $language,
        string $userAgent
    ) {
        $this->httpClient = $httpClient;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
        $this->baseUri = rtrim($baseUri, '/');
        $this->language = $language;
        $this->userAgent = $userAgent;
    }

    /**
     * @param array<string, string>     $headers additional headers, e.g. OTP or Clearance-Status
     * @param array<string, mixed>|null $payload JSON body
     *
     * @throws TransportException on network failure
     */
    public function send(
        string $method,
        string $path,
        array $headers = [],
        ?array $payload = null,
        ?Credentials $credentials = null
    ): HttpResponse {
        $request = $this->requestFactory
            ->createRequest($method, $this->baseUri . '/' . ltrim($path, '/'))
            ->withHeader('Accept', 'application/json')
            ->withHeader('Accept-Language', $this->language)
            ->withHeader('Accept-Version', self::API_VERSION)
            ->withHeader('User-Agent', $this->userAgent);

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        if ($credentials !== null) {
            $request = $request->withHeader('Authorization', $credentials->getAuthorizationHeader());
        }

        if ($payload !== null) {
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streamFactory->createStream($this->encodeJson($payload)));
        }

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw TransportException::fromClientException($e, self::redact($request));
        }

        return new HttpResponse($response, self::redact($request), self::decodeJson($response));
    }

    /**
     * Removes credentials and OTPs so the request can be safely logged or attached to exceptions.
     */
    private static function redact(RequestInterface $request): RequestInterface
    {
        foreach (self::SENSITIVE_HEADERS as $header) {
            if ($request->hasHeader($header)) {
                $request = $request->withHeader($header, '[REDACTED]');
            }
        }

        return $request;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function encodeJson(array $payload): string
    {
        try {
            return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InvalidArgumentException('Failed to encode request payload as JSON: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @return array<array-key, mixed>|null null when the body is empty or not a JSON object
     */
    private static function decodeJson(ResponseInterface $response): ?array
    {
        $body = trim((string) $response->getBody());

        if ($body === '') {
            return null;
        }

        try {
            // Large numeric IDs stay strings instead of losing precision as floats.
            $data = json_decode($body, true, 512, JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return null;
        }

        return is_array($data) ? $data : null;
    }
}
