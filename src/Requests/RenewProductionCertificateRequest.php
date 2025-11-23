<?php

namespace Sevaske\ZatcaApi\Requests;

use Sevaske\ZatcaApi\Enums\ZatcaEndpointEnum;

class RenewProductionCertificateRequest extends CertificateRequest
{
    public function uri(): string
    {
        return ZatcaEndpointEnum::PRODUCTION_CERTIFICATE;
    }
}