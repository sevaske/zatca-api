<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Requests;

use Sevaske\ZatcaApi\ZatcaEndpoint;

class ComplianceCertificateRequest extends CertificateRequest
{
    public function uri(): string
    {
        return ZatcaEndpoint::COMPLIANCE_CERTIFICATE;
    }
}
