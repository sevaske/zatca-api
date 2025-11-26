<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Requests;

use Sevaske\ZatcaApi\Interfaces\RequiresAuthTokenInterface;
use Sevaske\ZatcaApi\ZatcaEndpoint;

class ProductionCertificateRequest extends Request implements RequiresAuthTokenInterface
{
    public function __construct(int $complianceRequestId)
    {
        parent::__construct(ZatcaEndpoint::PRODUCTION_CERTIFICATE, [
            'body' => [
                'compliance_request_id' => $complianceRequestId,
            ],
        ]);
    }
}
