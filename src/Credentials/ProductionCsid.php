<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Credentials;

use Sevaske\ZatcaApi\Credentials\Concerns\HoldsCsid;
use Sevaske\ZatcaApi\Exception\InvalidArgumentException;

/**
 * Production CSID: authorizes reporting and clearance of invoices and its own renewal.
 */
final class ProductionCsid implements Credentials
{
    use HoldsCsid;

    /**
     * @throws InvalidArgumentException
     */
    public function __construct(string $binarySecurityToken, string $secret)
    {
        $this->assignCsid($binarySecurityToken, $secret);
    }

    /**
     * Restores credentials saved with {@see toArray()}.
     *
     * @param array<array-key, mixed> $data
     *
     * @throws InvalidArgumentException
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::readRequiredString($data, 'binarySecurityToken'),
            self::readRequiredString($data, 'secret'),
        );
    }

    /**
     * @return array{binarySecurityToken: string, secret: string}
     */
    public function toArray(): array
    {
        return [
            'binarySecurityToken' => $this->binarySecurityToken,
            'secret' => $this->secret,
        ];
    }
}
