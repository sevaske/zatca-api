<?php

namespace Sevaske\ZatcaApi\Requests;

use Sevaske\ZatcaApi\Enums\ZatcaEndpointEnum;

class ComplianceCertificateRequest extends CertificateRequest
{
    public function uri(): string
    {
        return ZatcaEndpointEnum::COMPLIANCE_CERTIFICATE;
    }
}