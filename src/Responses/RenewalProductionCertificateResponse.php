<?php

namespace Sevaske\ZatcaApi\Responses;

class RenewalProductionCertificateResponse extends ProductionCertificateResponse
{
    /**
     * HTTP 428: ZATCA issued a compliance certificate instead of a production one.
     * Pass the compliance checks with it, then call productionCertificate() with requestId().
     */
    public function isNotCompliant(): bool
    {
        return $this->getOptionalAttribute('dispositionMessage') === 'NOT_COMPLIANT';
    }

    public function tokenType(): ?string
    {
        return $this->getOptionalAttribute('tokenType');
    }
}
