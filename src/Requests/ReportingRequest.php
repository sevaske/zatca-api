<?php

namespace Sevaske\ZatcaApi\Requests;

use Sevaske\ZatcaApi\Enums\ZatcaEndpointEnum;

class ReportingRequest extends InvoiceRequest
{
    public function uri(): string
    {
        return ZatcaEndpointEnum::REPORTING;
    }
}