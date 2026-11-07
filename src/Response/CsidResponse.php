<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Response;

use Sevaske\ZatcaApi\Credentials\ComplianceCsid;
use Sevaske\ZatcaApi\Credentials\ProductionCsid;
use Sevaske\ZatcaApi\Exception\InvalidArgumentException;
use Sevaske\ZatcaApi\Response\Concerns\ReadsData;

/**
 * A CSID issued by ZATCA: compliance CSID, production CSID, or the result of a production CSID renewal.
 *
 * A renewal may answer with HTTP 428 "NOT_COMPLIANT": then a compliance CSID is issued instead
 * and the compliance checks must be passed again before requesting a production CSID.
 */
final class CsidResponse
{
    use ReadsData;

    public const DISPOSITION_ISSUED = 'ISSUED';

    public const DISPOSITION_NOT_COMPLIANT = 'NOT_COMPLIANT';

    private int $httpStatusCode;

    private string $requestId;

    private string $dispositionMessage;

    private string $binarySecurityToken;

    private string $secret;

    private ?string $tokenType;

    /**
     * @var array<array-key, mixed>
     */
    private array $data;

    /**
     * @param array<array-key, mixed> $data
     */
    public function __construct(
        int $httpStatusCode,
        string $requestId,
        string $dispositionMessage,
        string $binarySecurityToken,
        string $secret,
        ?string $tokenType = null,
        array $data = []
    ) {
        $this->httpStatusCode = $httpStatusCode;
        $this->requestId = $requestId;
        $this->dispositionMessage = $dispositionMessage;
        $this->binarySecurityToken = $binarySecurityToken;
        $this->secret = $secret;
        $this->tokenType = $tokenType;
        $this->data = $data;
    }

    /**
     * @param array<array-key, mixed> $data decoded response body
     */
    public static function fromArray(array $data, int $httpStatusCode = 200): self
    {
        return new self(
            $httpStatusCode,
            self::readString($data, 'requestID'),
            self::readString($data, 'dispositionMessage'),
            self::readString($data, 'binarySecurityToken'),
            self::readString($data, 'secret'),
            self::readNullableString($data, 'tokenType'),
            $data,
        );
    }

    public function isIssued(): bool
    {
        return $this->dispositionMessage === self::DISPOSITION_ISSUED;
    }

    /**
     * True when a renewal was answered with HTTP 428: a compliance CSID was issued instead,
     * see {@see toComplianceCsid()}.
     */
    public function isNotCompliant(): bool
    {
        return $this->dispositionMessage === self::DISPOSITION_NOT_COMPLIANT;
    }

    /**
     * @throws InvalidArgumentException when the response holds no complete credentials
     */
    public function toComplianceCsid(): ComplianceCsid
    {
        return new ComplianceCsid($this->binarySecurityToken, $this->secret, $this->requestId);
    }

    /**
     * @throws InvalidArgumentException when the response holds no complete credentials
     */
    public function toProductionCsid(): ProductionCsid
    {
        return new ProductionCsid($this->binarySecurityToken, $this->secret);
    }

    public function getHttpStatusCode(): int
    {
        return $this->httpStatusCode;
    }

    /**
     * "requestID": for a compliance CSID it is the ID to request the production CSID with.
     */
    public function getRequestId(): string
    {
        return $this->requestId;
    }

    /**
     * "ISSUED" or "NOT_COMPLIANT".
     */
    public function getDispositionMessage(): string
    {
        return $this->dispositionMessage;
    }

    public function getBinarySecurityToken(): string
    {
        return $this->binarySecurityToken;
    }

    public function getSecret(): string
    {
        return $this->secret;
    }

    /**
     * e.g. "http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-x509-token-profile-1.0#X509v3".
     */
    public function getTokenType(): ?string
    {
        return $this->tokenType;
    }

    /**
     * Decoded response body.
     *
     * @return array<array-key, mixed>
     */
    public function getData(): array
    {
        return $this->data;
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
        unset($info['data']);

        return $info;
    }
}
