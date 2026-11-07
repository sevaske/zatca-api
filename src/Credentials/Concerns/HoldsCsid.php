<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Credentials\Concerns;

use Sevaske\ZatcaApi\Exception\InvalidArgumentException;

/**
 * @internal
 */
trait HoldsCsid
{
    private string $binarySecurityToken;

    private string $secret;

    public function getBinarySecurityToken(): string
    {
        return $this->binarySecurityToken;
    }

    public function getSecret(): string
    {
        return $this->secret;
    }

    public function getCertificate(): string
    {
        return (string) base64_decode($this->binarySecurityToken, true);
    }

    public function getCertificatePem(): string
    {
        return "-----BEGIN CERTIFICATE-----\n"
            . chunk_split($this->getCertificate(), 64, "\n")
            . '-----END CERTIFICATE-----';
    }

    public function getAuthorizationHeader(): string
    {
        return 'Basic ' . base64_encode($this->binarySecurityToken . ':' . $this->secret);
    }

    /**
     * Keeps the secret out of var_dump() / print_r() output.
     *
     * @return array<array-key, mixed>
     */
    public function __debugInfo(): array
    {
        $info = get_object_vars($this);
        $info['secret'] = '[REDACTED]';

        return $info;
    }

    private function assignCsid(string $binarySecurityToken, string $secret): void
    {
        $binarySecurityToken = trim($binarySecurityToken);
        $secret = trim($secret);

        if ($binarySecurityToken === '' || base64_decode($binarySecurityToken, true) === false) {
            throw new InvalidArgumentException('The binarySecurityToken must be a non-empty base64 string as returned by ZATCA.');
        }

        if ($secret === '') {
            throw new InvalidArgumentException('The CSID secret must not be empty.');
        }

        $this->binarySecurityToken = $binarySecurityToken;
        $this->secret = $secret;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function readRequiredString(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        if (is_int($value)) {
            return (string) $value;
        }

        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException(sprintf('Missing or invalid "%s".', $key));
        }

        return $value;
    }
}
