<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Responses;

class RenewalProductionCertificateResponse extends ProductionCertificateResponse
{
    public function tokenType(): ?string
    {
        return $this->getOptionalAttribute('tokenType');
    }
}
