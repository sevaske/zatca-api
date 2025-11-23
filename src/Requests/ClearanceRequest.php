<?php

namespace Sevaske\ZatcaApi\Requests;

use Sevaske\ZatcaApi\ZatcaEndpointEnum;

class ClearanceRequest extends InvoiceRequest
{
    public function uri(): string
    {
        return ZatcaEndpointEnum::CLEARANCE;
    }
}