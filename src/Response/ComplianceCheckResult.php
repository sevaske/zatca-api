<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Response;

use Sevaske\ZatcaApi\Response\Concerns\ReadsData;

/**
 * Result of POST /compliance/invoices: compliance check of a sample invoice, credit note or debit note
 * during onboarding.
 */
final class ComplianceCheckResult
{
    use ReadsData;

    private int $httpStatusCode;

    private ?string $reportingStatus;

    private ?string $clearanceStatus;

    private ?string $qrSellerStatus;

    private ?string $qrBuyerStatus;

    private ValidationResults $validationResults;

    /**
     * @var array<array-key, mixed>
     */
    private array $data;

    /**
     * @param array<array-key, mixed> $data
     */
    public function __construct(
        int $httpStatusCode,
        ?string $reportingStatus,
        ?string $clearanceStatus,
        ?string $qrSellerStatus,
        ?string $qrBuyerStatus,
        ValidationResults $validationResults,
        array $data = []
    ) {
        $this->httpStatusCode = $httpStatusCode;
        $this->reportingStatus = $reportingStatus;
        $this->clearanceStatus = $clearanceStatus;
        $this->qrSellerStatus = $qrSellerStatus;
        $this->qrBuyerStatus = $qrBuyerStatus;
        $this->validationResults = $validationResults;
        $this->data = $data;
    }

    /**
     * @param array<array-key, mixed> $data decoded response body
     */
    public static function fromArray(array $data, int $httpStatusCode = 200): self
    {
        return new self(
            $httpStatusCode,
            self::readNullableString($data, 'reportingStatus'),
            self::readNullableString($data, 'clearanceStatus'),
            // ZATCA spells these keys "qrSellertStatus" and "qrBuyertStatus"
            self::readNullableString($data, 'qrSellertStatus') ?? self::readNullableString($data, 'qrSellerStatus'),
            self::readNullableString($data, 'qrBuyertStatus') ?? self::readNullableString($data, 'qrBuyerStatus'),
            ValidationResults::fromResponseData($data),
            $data,
        );
    }

    /**
     * True when the document passed the check: reported (simplified) or cleared (standard), without errors.
     */
    public function isPassed(): bool
    {
        $accepted = $this->reportingStatus === ReportingResult::STATUS_REPORTED
            || $this->clearanceStatus === ClearanceResult::STATUS_CLEARED;

        return $accepted && !$this->validationResults->hasErrors();
    }

    public function hasWarnings(): bool
    {
        return $this->validationResults->hasWarnings();
    }

    public function getHttpStatusCode(): int
    {
        return $this->httpStatusCode;
    }

    /**
     * For simplified documents: "REPORTED" or "NOT_REPORTED".
     */
    public function getReportingStatus(): ?string
    {
        return $this->reportingStatus;
    }

    /**
     * For standard documents: "CLEARED" or "NOT_CLEARED".
     */
    public function getClearanceStatus(): ?string
    {
        return $this->clearanceStatus;
    }

    public function getQrSellerStatus(): ?string
    {
        return $this->qrSellerStatus;
    }

    public function getQrBuyerStatus(): ?string
    {
        return $this->qrBuyerStatus;
    }

    public function getValidationResults(): ValidationResults
    {
        return $this->validationResults;
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
