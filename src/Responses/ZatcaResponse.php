<?php

namespace Sevaske\ZatcaApi\Responses;

use Psr\Http\Message\ResponseInterface;
use Sevaske\ZatcaApi\Exceptions\ZatcaException;
use Sevaske\ZatcaApi\Exceptions\ZatcaResponseException;
use Sevaske\ZatcaApi\Interfaces\ZatcaResponseInterface;
use Sevaske\ZatcaApi\Traits\HasAttributes;

class ZatcaResponse implements ZatcaResponseInterface
{
    use HasAttributes;

    /**
     * Constructs the ApiResponse object by parsing a PSR-7 response into attributes.
     *
     * @param  ResponseInterface|array  $response  The original PSR-7 HTTP response OR array.
     *
     * @throws ZatcaException If the response body cannot be parsed as valid JSON.
     */
    public function __construct(protected ResponseInterface|array $response)
    {
        if ($response instanceof ResponseInterface) {
            $this->attributes = self::parse($response);
        } else {
            $this->attributes = $this->response;
        }
    }

    public function raw(): ResponseInterface|array
    {
        return $this->response;
    }

    public function errors(): array
    {
        return [];
    }

    /**
     * Parses the PSR-7 response and returns an associative array of its JSON contents.
     *
     * @param  ResponseInterface  $response  The HTTP response to parse.
     * @return array The decoded JSON content as an array.
     *
     * @throws ZatcaResponseException If JSON decoding fails.
     */
    public static function parse(ResponseInterface $response): array
    {
        $body = $response->getBody();

        if ($body->isSeekable()) {
            $body->rewind();
        }

        $content = $body->getContents();
        $parsed = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ZatcaResponseException(json_last_error_msg(), [
                'content' => $content,
                'status' => $response->getStatusCode(),
            ]);
        }

        return (array) $parsed;
    }

    /**
     * HTTP status code of the response, null when the response was created from an array.
     */
    public function statusCode(): ?int
    {
        return $this->response instanceof ResponseInterface ? $this->response->getStatusCode() : null;
    }

    public function unauthorized(): bool
    {
        return $this->statusCode() === 401 || $this->getOptionalAttribute('status') === 401;
    }
}
