<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Credentials;

use Sevaske\ZatcaApi\Credentials\Concerns\HoldsCsid;
use Sevaske\ZatcaApi\Exception\InvalidArgumentException;

/**
 * Compliance CSID: authorizes compliance checks and the request for a production CSID.
 *
 * It cannot be used to report or clear real invoices.
 */
final class ComplianceCsid implements Credentials
{
    use HoldsCsid;

    private string $requestId;

    /**
     * @param string $requestId "requestID" returned with the compliance CSID, needed to request a production CSID
     *
     * @throws InvalidArgumentException
     */
    public function __construct(string $binarySecurityToken, string $secret, string $requestId)
    {
        $this->assignCsid($binarySecurityToken, $secret);

        $requestId = trim($requestId);

        if ($requestId === '') {
            throw new InvalidArgumentException('The compliance request ID must not be empty.');
        }

        $this->requestId = $requestId;
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
            self::readRequiredString($data, 'requestId'),
        );
    }

    public function getRequestId(): string
    {
        return $this->requestId;
    }

    /**
     * @return array{binarySecurityToken: string, secret: string, requestId: string}
     */
    public function toArray(): array
    {
        return [
            'binarySecurityToken' => $this->binarySecurityToken,
            'secret' => $this->secret,
            'requestId' => $this->requestId,
        ];
    }
}
