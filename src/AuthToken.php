<?php

namespace Sevaske\ZatcaApi;

class AuthToken
{
    protected string $certificate;

    protected string $secret;

    // Stores the encoded authorization token
    private string $token;

    /**
     * Constructor accepts a certificate and a secret,
     * then generates a token in the format base64(base64(certificate):secret)
     *
     * @param  string  $certificate  - The certificate string
     * @param  string  $secret  - The secret key
     */
    public function __construct(string $certificate, string $secret)
    {
        $this->secret = $secret;
        $this->certificate = $certificate;

        // Trim inputs, double base64 encode the certificate,
        // concatenate with secret separated by ':', then base64 encode the whole string
        $this->token = base64_encode(base64_encode(trim($certificate)).':'.trim($secret));
    }

    /**
     * Magic method to convert the object to a string.
     * Allows the object to be used in string context, e.g. echo or concatenation.
     */
    public function __toString(): string
    {
        return $this->token;
    }

    /**
     * Generates the HTTP Authorization header value in the format "Basic {token}".
     */
    public function toBasic(): string
    {
        return 'Basic '.$this->token;
    }

    /**
     * Returns an associative array representing HTTP headers,
     * with the 'Authorization' header set to the Basic auth string.
     */
    public function toHeader(): array
    {
        return [
            'Authorization' => $this->toBasic(),
        ];
    }
}
