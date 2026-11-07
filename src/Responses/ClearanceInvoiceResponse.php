<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Responses;

class ClearanceInvoiceResponse extends ValidationResponse
{
    /**
     * Determines whether the clearance was successful.
     *
     * @return bool True if the clearanceStatus is 'CLEARED', false otherwise.
     */
    public function success(): bool
    {
        return $this->status() === 'CLEARED';
    }

    public function status(): ?string
    {
        return $this->getOptionalAttribute('clearanceStatus');
    }

    public function clearedInvoice(): ?string
    {
        return $this->getOptionalAttribute('clearedInvoice');
    }

    /**
     * The cleared invoice XML (decoded from base64), or null if not returned.
     */
    public function clearedInvoiceXml(): ?string
    {
        $clearedInvoice = $this->clearedInvoice();

        if ($clearedInvoice === null || $clearedInvoice === '') {
            return null;
        }

        $xml = base64_decode($clearedInvoice, true);

        return $xml === false ? null : $xml;
    }

    /**
     * True when ZATCA answered 303: clearance is deactivated and the invoice
     * must be submitted through the reporting endpoint instead.
     */
    public function isClearanceDisabled(): bool
    {
        return $this->statusCode() === 303;
    }

    /**
     * Informational message returned by ZATCA (e.g. with HTTP 303).
     */
    public function message(): ?string
    {
        return $this->getOptionalAttribute('message');
    }
}
