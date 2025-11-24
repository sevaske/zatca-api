<?php
declare(strict_types=1);

namespace Sevaske\ZatcaApi\Requests;

use Sevaske\ZatcaApi\Interfaces\RequiresAuthTokenInterface;

abstract class InvoiceRequest extends Request implements RequiresAuthTokenInterface
{
    public function __construct(string $invoice, ?string $invoiceHash, string $uuid)
    {
        parent::__construct($this->uri(), [
            'body' => [
                'invoice' => base64_encode($invoice),
                'hash' => $this->normalizeInvoiceHash($invoiceHash),
                'uuid' => $uuid,
            ]
        ]);
    }

    private function normalizeInvoiceHash(?string $invoiceHash): string
    {
        return $invoiceHash ?? base64_encode('0');
    }
}