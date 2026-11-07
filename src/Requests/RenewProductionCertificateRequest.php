<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Requests;

use Sevaske\ZatcaApi\Interfaces\RequiresAuthTokenInterface;
use Sevaske\ZatcaApi\ZatcaEndpoint;

/**
 * PATCH /production/csids with the renewal OTP and a new CSR,
 * authorized by the current production certificate.
 */
class RenewProductionCertificateRequest extends CertificateRequest implements RequiresAuthTokenInterface
{
    public function uri(): string
    {
        return ZatcaEndpoint::PRODUCTION_CERTIFICATE;
    }

    public function method(): string
    {
        return 'PATCH';
    }
}
