<?php

namespace Sevaske\ZatcaApi\Traits;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
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

    public static function authToken(string $certificate, string $secret): string
    {
        $cleanCert = base64_encode(trim($certificate));

        return base64_encode($cleanCert.':'.trim($secret));
    }

    public function setCredentials(?string $certificate, ?string $secret): void
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
     * Core request handling with Guzzle.
     *
     * @param  string  $endpoint  API endpoint.
     * @param  array  $headers  Additional headers.
     * @param  array  $payload  Request payload.
     * @param  bool|AuthToken  $authToken  To add the auth token.
     * @param  string  $method  HTTP method.
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

        $uri = $this->baseUrl().$endpoint;
        $options = [
            'headers' => $headers,
            'json' => $payload,
        ];

        try {
            return $this->httpClient->request($method, $uri, $options);
        } catch (ClientExceptionInterface $e) {
            // PSR-18 does NOT guarantee a response object on exception
            $context = [
                'uri' => $uri,
                'method' => $method,
            ];

            throw new ZatcaRequestException($e->getMessage(), $context, $e->getCode(), $e);
        }
    }
}
