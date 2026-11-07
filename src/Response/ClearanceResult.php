<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Response;

use Sevaske\ZatcaApi\Response\Concerns\ReadsData;

/**
 * Result of POST /invoices/clearance/single.
 *
 * A rejected invoice is a result, not an exception: check {@see isCleared()} and the validation results.
 */
final class ClearanceResult
{
    use ReadsData;

    public const STATUS_CLEARED = 'CLEARED';

    public const STATUS_NOT_CLEARED = 'NOT_CLEARED';

    private int $httpStatusCode;

    private ?string $clearanceStatus;

    private ?string $clearedInvoiceBase64;

    private ValidationResults $validationResults;

    private ?string $message;

    /**
     * @var array<array-key, mixed>
     */
    private array $data;

    /**
     * @param array<array-key, mixed> $data
     */
    public function __construct(
        int $httpStatusCode,
        ?string $clearanceStatus,
        ?string $clearedInvoiceBase64,
        ValidationResults $validationResults,
        ?string $message = null,
        array $data = []
    ) {
        $this->httpStatusCode = $httpStatusCode;
        $this->clearanceStatus = $clearanceStatus;
        $this->clearedInvoiceBase64 = $clearedInvoiceBase64;
        $this->validationResults = $validationResults;
        $this->message = $message;
        $this->data = $data;
    }

    /**
     * @param array<array-key, mixed> $data decoded response body
     */
    public static function fromArray(array $data, int $httpStatusCode = 200): self
    {
        return new self(
            $httpStatusCode,
            self::readNullableString($data, 'clearanceStatus'),
            self::readNullableString($data, 'clearedInvoice'),
            ValidationResults::fromResponseData($data),
            self::readNullableString($data, 'message'),
            $data,
        );
    }

    /**
     * True when ZATCA cleared the invoice (HTTP 200, 202 with warnings, or 208 already cleared).
     */
    public function isCleared(): bool
    {
        return $this->clearanceStatus === self::STATUS_CLEARED;
    }

    /**
     * True when ZATCA answered HTTP 208: this invoice hash was cleared before; the cleared invoice is returned again.
     */
    public function isAlreadyCleared(): bool
    {
        return $this->httpStatusCode === 208;
    }

    /**
     * True when ZATCA answered HTTP 303: clearance is deactivated, submit the invoice through reporting
     * (with {@see \Sevaske\ZatcaApi\ClientOptions::withClearanceEnabled()} set to false).
     */
    public function isClearanceDisabled(): bool
    {
        return $this->httpStatusCode === 303;
    }

    public function hasWarnings(): bool
    {
        return $this->validationResults->hasWarnings();
    }

    /**
     * The cleared invoice XML, stamped by ZATCA and with its QR code (null when not cleared).
     * This is the document to hand over to the buyer.
     */
    public function getClearedInvoice(): ?string
    {
        if ($this->clearedInvoiceBase64 === null) {
            return null;
        }

        $xml = base64_decode($this->clearedInvoiceBase64, true);

        return $xml === false ? null : $xml;
    }

    /**
     * The cleared invoice exactly as returned by ZATCA (base64).
     */
    public function getClearedInvoiceBase64(): ?string
    {
        return $this->clearedInvoiceBase64;
    }

    public function getHttpStatusCode(): int
    {
        return $this->httpStatusCode;
    }

    /**
     * "CLEARED" or "NOT_CLEARED" (null when ZATCA did not report it, e.g. with HTTP 303).
     */
    public function getClearanceStatus(): ?string
    {
        return $this->clearanceStatus;
    }

    public function getValidationResults(): ValidationResults
    {
        return $this->validationResults;
    }

    /**
     * Informational message, e.g. "Clearance is deactivated..." with HTTP 303.
     */
    public function getMessage(): ?string
    {
        return $this->message;
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
}
