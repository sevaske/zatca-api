<?php

namespace Sevaske\ZatcaApi\Requests;

use Sevaske\ZatcaApi\ZatcaEndpointEnum;

class ComplianceCertificateRequest extends CertificateRequest
{
    public function uri(): string
    {
        return ZatcaEndpointEnum::COMPLIANCE_CERTIFICATE;
    }
}