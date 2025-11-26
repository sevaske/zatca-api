<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Responses;

class ComplianceInvoiceResponse extends ValidationResponse
{
    public function status(): ?string
    {
        return $this->getOptionalAttribute('status');
    }

    public function reportingStatus(): ?string
    {
        return $this->getOptionalAttribute('reportingStatus');
    }

    public function clearanceStatus(): ?string
    {
        return $this->getOptionalAttribute('clearanceStatus');
    }

    public function qrSellertStatus(): ?string
    {
        return $this->getOptionalAttribute('qrSellertStatus');
    }

    public function qrBuyertStatus(): ?string
    {
        return $this->getOptionalAttribute('qrBuyertStatus');
    }
}
