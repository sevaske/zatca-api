<?php

namespace Sevaske\ZatcaApi\Requests;

use Sevaske\ZatcaApi\Enums\ZatcaEndpointEnum;

class ComplianceInvoiceRequest extends InvoiceRequest
{
    public function uri(): string
    {
        return ZatcaEndpointEnum::COMPLIANCE_INVOICE;
    }
}