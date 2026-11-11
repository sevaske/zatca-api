<?php

namespace Sevaske\ZatcaApi\Responses;

class ComplianceResponse extends ValidationResponse
{
    /**
     * Determines whether the invoice passed the compliance check:
     * simplified invoices are REPORTED, standard invoices are CLEARED.
     */
    public function success(): bool
    {
        return $this->reportingStatus() === 'REPORTED' || $this->clearanceStatus() === 'CLEARED';
    }

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
