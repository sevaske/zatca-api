<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Responses;

class CertificateResponse extends ZatcaResponse
{
    public function success(): bool
    {
        return $this->getOptionalAttribute('dispositionMessage') === 'ISSUED';
    }

    /**
     * ZATCA disposition message, e.g. "ISSUED" or "NOT_COMPLIANT".
     */
    public function dispositionMessage(): ?string
    {
        return $this->getOptionalAttribute('dispositionMessage');
    }

    /**
     * True when ZATCA requires the compliance checks to be passed again (HTTP 428 on renewal).
     * In this case the returned certificate is a compliance certificate.
     */
    public function isNotCompliant(): bool
    {
        return $this->dispositionMessage() === 'NOT_COMPLIANT';
    }

    public function tokenType(): ?string
    {
        return $this->getOptionalAttribute('tokenType');
    }

    public function requestId(): ?int
    {
        if ($requestId = $this->getOptionalAttribute('requestID')) {
            return (int) $requestId;
        }

        return null;
    }

    public function secret(): ?string
    {
        return $this->getOptionalAttribute('secret');
    }

    public function binarySecurityToken(): ?string
    {
        return $this->getOptionalAttribute('binarySecurityToken');
    }

    public function certificate(): ?string
    {
        return (string) base64_decode((string) $this->binarySecurityToken());
    }

    public function errors(): array
    {
        return (array) $this->getOptionalAttribute('errors');
    }

    public function hasErrors(): bool
    {
        return ! empty($this->errors());
    }
}
