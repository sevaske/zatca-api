<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Exception;

use Sevaske\ZatcaApi\Http\HttpResponse;

/**
 * Thrown when a response cannot be understood (invalid JSON, missing fields, ...).
 */
class UnexpectedResponseException extends ApiException
{
    /**
     * @internal
     */
    public static function invalidBody(HttpResponse $httpResponse, string $reason): self
    {
        return new self(
            sprintf('ZATCA API returned an unexpected response (HTTP %d): %s', $httpResponse->getStatusCode(), $reason),
            $httpResponse->getStatusCode(),
            $httpResponse->getData(),
            [],
            $httpResponse->getResponse(),
            $httpResponse->getRequest(),
        );
    }
}
