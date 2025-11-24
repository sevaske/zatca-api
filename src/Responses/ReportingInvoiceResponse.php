<?php
declare(strict_types=1);

namespace Sevaske\ZatcaApi\Responses;

/**
 * Class ReportingResponse
 *
 * Represents a response from the ZATCA reporting API.
 * Provides convenient methods to access status, validation results, warnings, and errors.
 */
class ReportingInvoiceResponse extends ValidationResponse
{
    /**
     * Determines whether the reporting was successful.
     *
     * @return bool True if the reportingStatus is 'REPORTED', false otherwise.
     */
    public function success(): bool
    {
        return $this->status() === 'REPORTED';
    }

    public function status(): ?string
    {
        return $this->getOptionalAttribute('reportingStatus');
    }
}
