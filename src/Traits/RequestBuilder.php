<?php

namespace Sevaske\ZatcaApi\Traits;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Sevaske\ZatcaApi\AuthToken;
use Sevaske\ZatcaApi\Exceptions\ZatcaRequestException;
use Sevaske\ZatcaApi\Exceptions\ZatcaResponseException;

trait RequestBuilder
{
    /**
     * @var string Base API url.
     */
    protected string $baseUrl;

    /**
     * HTTP client instance for sending requests.
     */
    private ClientInterface $httpClient;

    protected ?AuthToken $authToken = null;

    /**
     * Get the HTTP client instance.
     *
     * @return ClientInterface The HTTP client.
     */
    protected function httpClient(): ClientInterface
    {
        return $this->httpClient;
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    public static function authToken(#[\SensitiveParameter] string $certificate, #[\SensitiveParameter] string $secret): string
    {
        $cleanCert = base64_encode(trim($certificate));

        return base64_encode($cleanCert.':'.trim($secret));
    }

    public function setCredentials(#[\SensitiveParameter] ?string $certificate, #[\SensitiveParameter] ?string $secret): void
    {
        $this->certificate = $certificate;
        $this->secret = $secret;

        if ($this->certificate && $this->secret) {
            $this->authToken = new AuthToken($this->certificate, $this->secret);
        } else {
            $this->authToken = null;
        }
    }

    /**
     * Core request handling: Guzzle-like clients via request(), any other PSR-18 client via sendRequest().
     *
     * @param  string  $endpoint  API endpoint.
     * @param  array  $headers  Additional headers.
     * @param  array  $payload  Request payload.
     * @param  bool|AuthToken  $authToken  To add the auth token.
     * @param  string  $method  HTTP method.
     * @param  int[]  $expectedStatuses  HTTP error statuses to return as a response instead of throwing.
     * @return ResponseInterface Response.
     *
     * @throws ZatcaRequestException|ZatcaResponseException
     */
    private function request(
        string $endpoint = '',
        array $payload = [],
        array $headers = [],
        bool|AuthToken $authToken = false,
        string $method = 'POST',
        array $expectedStatuses = [],
    ): ResponseInterface {
        $headers = array_merge([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Accept-Language' => 'en',
            'Accept-Version' => 'V2',
        ], $headers);

        if ($authToken) {
            $token = $authToken instanceof AuthToken ? $authToken : $this->authToken;

            if (! $token instanceof AuthToken) {
                throw new ZatcaRequestException('The certificate is not passed. Unable to set the Authorization.', [
                    'method' => __METHOD__,
                    'endpoint' => $endpoint,
                ]);
            }

            $headers['Authorization'] = $token->toBasic();
        }

        // PSR-7 requires header values to be strings
        $headers = array_map(static fn ($value): string => (string) $value, $headers);
        $uri = $this->baseUrl().$endpoint;

        if (! method_exists($this->httpClient, 'request')) {
            // Pure PSR-18 client (no Guzzle-like request() method)
            return $this->sendPsrRequest($method, $uri, $headers, $payload);
        }

        $options = [
            'headers' => $headers,
            'json' => $payload,
            // ZATCA never redirects to another location. HTTP 303 on clearance is an answer, not a redirect.
            'allow_redirects' => false,
        ];

        try {
            return $this->httpClient->request($method, $uri, $options);
        } catch (ClientExceptionInterface $e) {
            // PSR-18 does NOT guarantee a response object on exception
            $response = $this->responseFromException($e);

            if ($response !== null && in_array($response->getStatusCode(), $expectedStatuses, true)) {
                return $response;
            }

            $context = [
                'uri' => $uri,
                'method' => $method,
            ];

            if ($response !== null) {
                $context = array_merge($context, $this->errorResponseContext($response));
            }

            throw new ZatcaRequestException($e->getMessage(), $context, (int) $e->getCode(), $e);
        }
    }

    /**
     * Sends the request through a pure PSR-18 client.
     *
     * @throws ZatcaRequestException
     */
    private function sendPsrRequest(string $method, string $uri, array $headers, array $payload): ResponseInterface
    {
        $context = [
            'uri' => $uri,
            'method' => $method,
        ];

        try {
            $body = json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ZatcaRequestException('Unable to encode the request payload: '.$e->getMessage(), $context, 0, $e);
        }

        $request = null;

        foreach (['GuzzleHttp\Psr7\Request', 'Nyholm\Psr7\Request'] as $requestClass) {
            if (class_exists($requestClass)) {
                $request = new $requestClass($method, $uri, $headers, $body);

                break;
            }
        }

        if (! $request instanceof RequestInterface) {
            throw new ZatcaRequestException(
                'Unable to build the HTTP request. Use a Guzzle client or install guzzlehttp/psr7 or nyholm/psr7.',
                $context,
            );
        }

        try {
            return $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new ZatcaRequestException($e->getMessage(), $context, (int) $e->getCode(), $e);
        }
    }

    /**
     * Extracts the HTTP response from a client exception when the client provides it (e.g. Guzzle on 4xx/5xx).
     */
    private function responseFromException(\Throwable $e): ?ResponseInterface
    {
        if (! method_exists($e, 'getResponse')) {
            return null;
        }

        $response = $e->getResponse();

        return $response instanceof ResponseInterface ? $response : null;
    }

    /**
     * Describes the HTTP error response for the exception context.
     *
     * @return array{status: int, content: string, response?: array, errors?: array}
     */
    private function errorResponseContext(ResponseInterface $response): array
    {
        $context = [
            'status' => $response->getStatusCode(),
            'content' => '',
        ];

        try {
            $body = $response->getBody();

            if ($body->isSeekable()) {
                $body->rewind();
            }

            $context['content'] = $body->getContents();

            if ($body->isSeekable()) {
                $body->rewind();
            }
        } catch (\Throwable) {
            return $context;
        }

        $decoded = json_decode($context['content'], true);

        if (! is_array($decoded)) {
            return $context;
        }

        $context['response'] = $decoded;

        // invoices: validationResults.errorMessages, certificates: errors
        $errors = $decoded['validationResults']['errorMessages'] ?? $decoded['errors'] ?? null;

        if (is_array($errors) && $errors !== []) {
            $context['errors'] = $errors;
        }

        return $context;
    }
}
