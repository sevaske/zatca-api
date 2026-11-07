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

    protected function defaultHeaders(): array
    {
        return array_merge(parent::defaultHeaders(), [
            // required by ZATCA: "1" means clearance is enabled
            'Clearance-Status' => '1',
        ]);
    }
}
