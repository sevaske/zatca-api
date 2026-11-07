<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Requests;

use Sevaske\ZatcaApi\ZatcaEndpoint;

class ReportingInvoiceRequest extends InvoiceRequest
{
    protected bool $clearanceStatus;

    /**
     * @param  bool  $clearanceStatus  Value of the required "Clearance-Status" header:
     *                                 true ("1") when clearance is enabled for the taxpayer,
     *                                 false ("0") when clearance is disabled.
     */
    public function __construct(string $invoice, string $invoiceHash, string $uuid, bool $clearanceStatus = true)
    {
        $this->clearanceStatus = $clearanceStatus;

        parent::__construct($invoice, $invoiceHash, $uuid);
    }

    public function uri(): string
    {
        return ZatcaEndpoint::REPORTING;
    }

    protected function defaultHeaders(): array
    {
        return array_merge(parent::defaultHeaders(), [
            'Clearance-Status' => $this->clearanceStatus ? '1' : '0',
        ]);
    }
}
