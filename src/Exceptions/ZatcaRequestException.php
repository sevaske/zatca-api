<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Exceptions;

use Psr\Http\Message\ResponseInterface;

class ZatcaRequestException extends ZatcaException
{
    /**
     * The HTTP response returned by ZATCA, if the request failed with an HTTP error status.
     */
    public function response(): ?ResponseInterface
    {
        $response = $this->context()['response'] ?? null;

        return $response instanceof ResponseInterface ? $response : null;
    }

    /**
     * The HTTP status code returned by ZATCA, if any.
     */
    public function statusCode(): ?int
    {
        $response = $this->response();

        return $response ? $response->getStatusCode() : null;
    }

    /**
     * The decoded JSON body of the ZATCA error response, if any.
     */
    public function data(): ?array
    {
        $data = $this->context()['data'] ?? null;

        return is_array($data) ? $data : null;
    }

    /**
     * Error messages returned by ZATCA: validationResults.errorMessages for invoices,
     * errors for certificate requests, or a single error object (401/500 responses).
     */
    public function errors(): array
    {
        $data = $this->data();

        if ($data === null) {
            return [];
        }

        if (isset($data['validationResults']['errorMessages']) && is_array($data['validationResults']['errorMessages'])) {
            return $data['validationResults']['errorMessages'];
        }

        if (isset($data['errors']) && is_array($data['errors'])) {
            return $data['errors'];
        }

        if (isset($data['message']) || isset($data['code']) || isset($data['error'])) {
            return [$data];
        }

        return [];
    }
}
