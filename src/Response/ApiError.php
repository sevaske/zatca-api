<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Response;

use Sevaske\ZatcaApi\Response\Concerns\ReadsData;

/**
 * An error reported by ZATCA outside of invoice validation, e.g. {"code": "Invalid-OTP", "message": "..."}.
 */
final class ApiError
{
    use ReadsData;

    private ?string $code;

    private string $message;

    private ?string $category;

    public function __construct(?string $code, string $message, ?string $category = null)
    {
        $this->code = $code;
        $this->message = $message;
        $this->category = $category;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::readNullableString($data, 'code'),
            self::readString($data, 'message'),
            self::readNullableString($data, 'category'),
        );
    }

    /**
     * Error code, e.g. "Invalid-OTP", "Missing-CSR", "Invalid-ComplianceRequestId".
     */
    public function getCode(): ?string
    {
        return $this->code;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }
}
