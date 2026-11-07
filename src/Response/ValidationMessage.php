<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Response;

use Sevaske\ZatcaApi\Response\Concerns\ReadsData;

/**
 * A single info, warning or error message of ZATCA invoice validation.
 *
 *     {"type": "ERROR", "code": "BR-KSA-37", "category": "KSA", "message": "...", "status": "ERROR"}
 */
final class ValidationMessage
{
    use ReadsData;

    public const TYPE_INFO = 'INFO';

    public const TYPE_WARNING = 'WARNING';

    public const TYPE_ERROR = 'ERROR';

    private string $type;

    private ?string $code;

    private ?string $category;

    private string $message;

    private ?string $status;

    public function __construct(string $type, ?string $code, ?string $category, string $message, ?string $status = null)
    {
        $this->type = $type;
        $this->code = $code;
        $this->category = $category;
        $this->message = $message;
        $this->status = $status;
    }

    /**
     * @param array<array-key, mixed> $data
     * @param string                  $defaultType type to use when the message does not specify it
     */
    public static function fromArray(array $data, string $defaultType = self::TYPE_ERROR): self
    {
        return new self(
            self::readString($data, 'type', $defaultType),
            self::readNullableString($data, 'code'),
            self::readNullableString($data, 'category'),
            self::readString($data, 'message'),
            self::readNullableString($data, 'status'),
        );
    }

    /**
     * INFO, WARNING or ERROR.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Rule or error code, e.g. "BR-KSA-37", "invalid-invoice-hash", "XSD_ZATCA_VALID".
     */
    public function getCode(): ?string
    {
        return $this->code;
    }

    /**
     * e.g. "KSA", "EN_16931", "XSD validation", "QRCODE_VALIDATION", "SIGNATURE_ERRORS".
     */
    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * PASS, WARNING or ERROR.
     */
    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function __toString(): string
    {
        return $this->code !== null ? sprintf('[%s] %s', $this->code, $this->message) : $this->message;
    }
}
