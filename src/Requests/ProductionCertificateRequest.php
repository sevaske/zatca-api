<?php

namespace Sevaske\ZatcaApi\Requests;

use Sevaske\ZatcaApi\Enums\ZatcaEndpointEnum;

class ProductionCertificateRequest extends Request
{
    public function __construct(string $complianceRequestId)
    {
        parent::__construct(ZatcaEndpointEnum::PRODUCTION_CERTIFICATE, [
            'json' => [
                'compliance_request_id' => $complianceRequestId,
            ],
        ]);
    }
}