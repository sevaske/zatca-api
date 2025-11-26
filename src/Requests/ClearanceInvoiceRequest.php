<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Requests;

use Sevaske\ZatcaApi\ZatcaEndpoint;

class ClearanceInvoiceRequest extends InvoiceRequest
{
    public function uri(): string
    {
        return ZatcaEndpoint::CLEARANCE;
    }
}
