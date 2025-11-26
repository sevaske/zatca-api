<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Responses;

class CertificateResponse extends ZatcaResponse
{
    public function success(): bool
    {
        return $this->getOptionalAttribute('dispositionMessage') === 'ISSUED';
    }

    public function requestId(): ?string
    {
        return $this->getOptionalAttribute('requestID');
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
