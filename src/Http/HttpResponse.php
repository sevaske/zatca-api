<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Http;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A received HTTP response with its decoded JSON body.
 *
 * @internal not covered by the backward compatibility promise
 */
final class HttpResponse
{
    private ResponseInterface $response;

    private RequestInterface $request;

    /**
     * @var array<array-key, mixed>|null
     */
    private ?array $data;

    /**
     * @param array<array-key, mixed>|null $data decoded JSON object body, null when empty or not JSON
     * @param RequestInterface             $request the sent request with sensitive headers redacted
     */
    public function __construct(ResponseInterface $response, RequestInterface $request, ?array $data)
    {
        $this->response = $response;
        $this->request = $request;
        $this->data = $data;
    }

    public function getStatusCode(): int
    {
        return $this->response->getStatusCode();
    }

    public function isStatus(int ...$statusCodes): bool
    {
        return in_array($this->getStatusCode(), $statusCodes, true);
    }

    public function hasData(): bool
    {
        return $this->data !== null;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getData(): array
    {
        return $this->data ?? [];
    }

    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
