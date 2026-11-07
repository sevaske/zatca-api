<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Contracts;

use Sevaske\ZatcaApi\Exception\ApiException;
use Sevaske\ZatcaApi\Exception\TransportException;
use Sevaske\ZatcaApi\Invoice\SignedInvoice;
use Sevaske\ZatcaApi\Response\ClearanceResult;
use Sevaske\ZatcaApi\Response\ReportingResult;

/**
 * Submits invoices to ZATCA. Type-hint it in your services to replace the API with a fake in tests.
 */
interface InvoiceSubmitter
{
    /**
     * Reports a simplified (B2C) invoice, credit note or debit note.
     *
     * @throws TransportException
     * @throws ApiException
     */
    public function report(SignedInvoice $invoice): ReportingResult;

    /**
     * Clears a standard (B2B) invoice, credit note or debit note.
     *
     * @throws TransportException
     * @throws ApiException
     */
    public function clear(SignedInvoice $invoice): ClearanceResult;
}
