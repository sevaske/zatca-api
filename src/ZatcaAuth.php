<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi;

use Sevaske\ZatcaApi\Interfaces\AuthTokenInterface;

/**
 * Represents a ZATCA Basic Authentication token.
 *
 * The token is generated from a certificate and a secret key,
 * using the following format:
 *
 *     base64( base64(certificate) : secret )
 *
 * This token can then be used as a Basic Authorization header value.
 */
class ZatcaAuth implements AuthTokenInterface
{
    /**
     * The original certificate string.
     */
    protected string $certificate;

    /**
     * The secret key associated with the certificate.
     */
    protected string $secret;

    /**
     * The encoded authorization token.
     */
    private string $token;

    /**
     * Creates a new AuthToken instance.
     *
     * @param  string  $certificate  The certificate (typically PEM or raw string)
     * @param  string  $secret  The secret key provided by ZATCA
     */
    public function __construct(string $certificate, string $secret)
    {
        $this->certificate = trim($certificate);
        $this->secret = trim($secret);

        // Generate token in the format base64( base64(certificate) : secret )
        $this->token = base64_encode(base64_encode($this->certificate).':'.$this->secret);
    }

    /**
     * Returns the raw encoded token (without "Basic " prefix).
     */
    public function token(): string
    {
        return $this->token;
    }

    /**
     * Returns the token in HTTP Basic Authorization format: "Basic {token}".
     */
    public function toBasic(): string
    {
        return 'Basic '.$this->token;
    }

    /**
     * Returns an associative array suitable for use in HTTP headers.
     *
     * Example:
     *     ['Authorization' => 'Basic <token>']
     */
    public function toHeader(): array
    {
        return ['Authorization' => $this->toBasic()];
    }

    /**
     * Returns the token string when the object is used in a string context.
     */
    public function __toString(): string
    {
        return $this->token;
    }
}
