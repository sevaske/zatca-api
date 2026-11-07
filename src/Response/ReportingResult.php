<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Response;

use Sevaske\ZatcaApi\Response\Concerns\ReadsData;

/**
 * Result of POST /invoices/reporting/single.
 *
 * A rejected invoice is a result, not an exception: check {@see isReported()} and the validation results.
 */
final class ReportingResult
{
    use ReadsData;

    public const STATUS_REPORTED = 'REPORTED';

    public const STATUS_NOT_REPORTED = 'NOT_REPORTED';

    private int $httpStatusCode;

    private ?string $reportingStatus;

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
        ?string $reportingStatus,
        ValidationResults $validationResults,
        ?string $message = null,
        array $data = []
    ) {
        $this->httpStatusCode = $httpStatusCode;
        $this->reportingStatus = $reportingStatus;
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
            self::readNullableString($data, 'reportingStatus'),
            ValidationResults::fromResponseData($data),
            self::readNullableString($data, 'message'),
            $data,
        );
    }

    /**
     * True when ZATCA accepted the invoice (HTTP 200, or 202 with warnings).
     */
    public function isReported(): bool
    {
        return $this->reportingStatus === self::STATUS_REPORTED;
    }

    /**
     * True when ZATCA answered HTTP 409: the invoice was already reported earlier.
     */
    public function isAlreadyReported(): bool
    {
        return $this->httpStatusCode === 409;
    }

    /**
     * True when ZATCA answered HTTP 303: a standard invoice was submitted for reporting while clearance
     * is enabled. Submit it with {@see \Sevaske\ZatcaApi\Api\Invoices::clear()} instead.
     */
    public function isClearanceRequired(): bool
    {
        return $this->httpStatusCode === 303;
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
     * "REPORTED" or "NOT_REPORTED" (null when ZATCA did not report it, e.g. with HTTP 303).
     */
    public function getReportingStatus(): ?string
    {
        return $this->reportingStatus;
    }

    public function getValidationResults(): ValidationResults
    {
        return $this->validationResults;
    }

    /**
     * Informational message, e.g. with HTTP 303.
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
