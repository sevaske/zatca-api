<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Requests;

use Sevaske\ZatcaApi\ZatcaEndpoint;

class ReportingInvoiceRequest extends InvoiceRequest
{
    public function uri(): string
    {
        return ZatcaEndpoint::REPORTING;
    }
}
