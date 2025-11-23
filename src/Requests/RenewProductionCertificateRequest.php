<?php

namespace Sevaske\ZatcaApi\Requests;

use Sevaske\ZatcaApi\ZatcaEndpointEnum;

class RenewProductionCertificateRequest extends CertificateRequest
{
    public function uri(): string
    {
        return ZatcaEndpointEnum::PRODUCTION_CERTIFICATE;
    }
}