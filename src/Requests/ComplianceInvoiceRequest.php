<?php

namespace Sevaske\ZatcaApi\Requests;

use Sevaske\ZatcaApi\ZatcaEndpointEnum;

class ComplianceInvoiceRequest extends InvoiceRequest
{
    public function uri(): string
    {
        return ZatcaEndpointEnum::COMPLIANCE_INVOICE;
    }
}