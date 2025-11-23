<?php

namespace Sevaske\ZatcaApi\Requests;

use Sevaske\ZatcaApi\ZatcaEndpointEnum;

class ProductionCertificateRequest extends Request
{
    public function __construct(string $complianceRequestId)
    {
        parent::__construct(ZatcaEndpointEnum::PRODUCTION_CERTIFICATE, [
            'body' => [
                'compliance_request_id' => $complianceRequestId,
            ],
        ]);
    }
}