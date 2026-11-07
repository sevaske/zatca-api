<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Response;

use Sevaske\ZatcaApi\Response\Concerns\ReadsData;

/**
 * Result of ZATCA invoice validation (XSD, EN 16931, KSA rules, QR code, signature, previous invoice hash).
 */
final class ValidationResults
{
    use ReadsData;

    public const STATUS_PASS = 'PASS';

    public const STATUS_WARNING = 'WARNING';

    public const STATUS_ERROR = 'ERROR';

    private ?string $status;

    /**
     * @var list<ValidationMessage>
     */
    private array $infoMessages;

    /**
     * @var list<ValidationMessage>
     */
    private array $warningMessages;

    /**
     * @var list<ValidationMessage>
     */
    private array $errorMessages;

    /**
     * @param list<ValidationMessage> $infoMessages
     * @param list<ValidationMessage> $warningMessages
     * @param list<ValidationMessage> $errorMessages
     */
    public function __construct(?string $status, array $infoMessages = [], array $warningMessages = [], array $errorMessages = [])
    {
        $this->status = $status;
        $this->infoMessages = $infoMessages;
        $this->warningMessages = $warningMessages;
        $this->errorMessages = $errorMessages;
    }

    /**
     * Reads "validationResults" from a response body; falls back to the legacy
     * {"status": ..., "warnings": [...], "errors": [...]} format.
     *
     * @param array<array-key, mixed> $responseData decoded response body
     */
    public static function fromResponseData(array $responseData): self
    {
        $validation = $responseData['validationResults'] ?? null;

        if (is_array($validation)) {
            return new self(
                self::readNullableString($validation, 'status'),
                self::messages($validation, 'infoMessages', ValidationMessage::TYPE_INFO),
                self::messages($validation, 'warningMessages', ValidationMessage::TYPE_WARNING),
                self::messages($validation, 'errorMessages', ValidationMessage::TYPE_ERROR),
            );
        }

        $errors = self::messages($responseData, 'errors', ValidationMessage::TYPE_ERROR);
        $warnings = self::messages($responseData, 'warnings', ValidationMessage::TYPE_WARNING);

        $status = null;
        if ($errors !== []) {
            $status = self::STATUS_ERROR;
        } elseif ($warnings !== []) {
            $status = self::STATUS_WARNING;
        }

        return new self($status, [], $warnings, $errors);
    }

    /**
     * Whether a response body contains validation results (current or legacy format).
     *
     * @param array<array-key, mixed> $responseData
     */
    public static function existIn(array $responseData): bool
    {
        return is_array($responseData['validationResults'] ?? null)
            || is_array($responseData['errors'] ?? null)
            || is_array($responseData['warnings'] ?? null);
    }

    /**
     * PASS, WARNING or ERROR (null when ZATCA did not report it).
     */
    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function isPassed(): bool
    {
        return $this->status === self::STATUS_PASS || ($this->status === null && !$this->hasErrors() && !$this->hasWarnings());
    }

    public function hasWarnings(): bool
    {
        return $this->warningMessages !== [];
    }

    public function hasErrors(): bool
    {
        return $this->errorMessages !== [];
    }

    /**
     * @return list<ValidationMessage>
     */
    public function getInfoMessages(): array
    {
        return $this->infoMessages;
    }

    /**
     * @return list<ValidationMessage>
     */
    public function getWarningMessages(): array
    {
        return $this->warningMessages;
    }

    /**
     * @return list<ValidationMessage>
     */
    public function getErrorMessages(): array
    {
        return $this->errorMessages;
    }

    /**
     * Codes of the error messages, e.g. ["BR-KSA-37", "invalid-invoice-hash"].
     *
     * @return list<string>
     */
    public function getErrorCodes(): array
    {
        $codes = [];

        foreach ($this->errorMessages as $message) {
            if ($message->getCode() !== null) {
                $codes[] = $message->getCode();
            }
        }

        return $codes;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return list<ValidationMessage>
     */
    private static function messages(array $data, string $key, string $type): array
    {
        $messages = [];

        foreach (self::readObjectList($data, $key) as $item) {
            $messages[] = ValidationMessage::fromArray($item, $type);
        }

        return $messages;
    }
}
