<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Exception;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestInterface;

/**
 * Thrown when the HTTP request could not be completed (DNS failure, timeout, connection refused, ...).
 *
 * No response from ZATCA was received, so it is unknown whether an invoice was accepted.
 * Resubmitting the same invoice is safe: ZATCA identifies invoices by their hash.
 */
class TransportException extends \RuntimeException implements ZatcaException
{
    private RequestInterface $request;

    public function __construct(string $message, RequestInterface $request, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);

        $this->request = $request;
    }

    /**
     * @param RequestInterface $request the request with sensitive headers redacted
     */
    public static function fromClientException(ClientExceptionInterface $exception, RequestInterface $request): self
    {
        return new self('Failed to send request to the ZATCA API: ' . $exception->getMessage(), $request, $exception);
    }

    /**
     * The failed request; the Authorization and OTP headers are redacted.
     */
    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
