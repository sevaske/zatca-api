<?php
declare(strict_types=1);

namespace Sevaske\ZatcaApi\Requests;

use Sevaske\ZatcaApi\ZatcaEndpoint;

class RenewProductionCertificateRequest extends CertificateRequest
{
    public function uri(): string
    {
        return ZatcaEndpoint::PRODUCTION_CERTIFICATE;
    }
}