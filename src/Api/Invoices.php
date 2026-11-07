<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Api;

use Sevaske\ZatcaApi\Contracts\InvoiceSubmitter;
use Sevaske\ZatcaApi\Credentials\ProductionCsid;
use Sevaske\ZatcaApi\Exception\ApiException;
use Sevaske\ZatcaApi\Exception\TransportException;
use Sevaske\ZatcaApi\Exception\UnexpectedResponseException;
use Sevaske\ZatcaApi\Http\HttpResponse;
use Sevaske\ZatcaApi\Http\HttpTransport;
use Sevaske\ZatcaApi\Invoice\SignedInvoice;
use Sevaske\ZatcaApi\Response\ClearanceResult;
use Sevaske\ZatcaApi\Response\ReportingResult;
use Sevaske\ZatcaApi\Response\ValidationResults;

/**
 * Reporting and clearance of real invoices with a production CSID.
 *
 * Validation outcomes are results, not exceptions: a rejected invoice (HTTP 400), an already
 * reported invoice (409), "use the other endpoint" (303) are returned as result objects.
 */
final class Invoices implements InvoiceSubmitter
{
    private const REPORTING_PATH = '/invoices/reporting/single';

    private const CLEARANCE_PATH = '/invoices/clearance/single';

    private HttpTransport $transport;

    private ProductionCsid $csid;

    private bool $clearanceEnabled;

    /**
     * @internal use {@see \Sevaske\ZatcaApi\Client::invoices()}
     */
    public function __construct(HttpTransport $transport, ProductionCsid $csid, bool $clearanceEnabled)
    {
        $this->transport = $transport;
        $this->csid = $csid;
        $this->clearanceEnabled = $clearanceEnabled;
    }

    /**
     * Reports a simplified (B2C) invoice, credit note or debit note within 24 hours of issuing it.
     * While clearance is disabled, standard invoices are reported as well.
     *
     * @throws TransportException on network failure; resubmitting the same invoice is safe
     * @throws ApiException       e.g. AuthenticationException for wrong credentials, ServerException
     */
    public function report(SignedInvoice $invoice): ReportingResult
    {
        $response = $this->submit(self::REPORTING_PATH, $invoice, $this->clearanceEnabled);

        if (!self::isResult($response, [200, 202, 303, 409])) {
            throw ApiException::fromHttpResponse($response);
        }

        return ReportingResult::fromArray($response->getData(), $response->getStatusCode());
    }

    /**
     * Clears a standard (B2B) invoice, credit note or debit note before it is handed over to the buyer.
     * On success, hand over {@see ClearanceResult::getClearedInvoice()}: the invoice stamped by ZATCA.
     *
     * @throws TransportException on network failure; resubmitting the same invoice is safe
     * @throws ApiException       e.g. AuthenticationException for wrong credentials, ServerException
     */
    public function clear(SignedInvoice $invoice): ClearanceResult
    {
        $response = $this->submit(self::CLEARANCE_PATH, $invoice, true);

        if (!self::isResult($response, [200, 202, 208, 303])) {
            throw ApiException::fromHttpResponse($response);
        }

        return ClearanceResult::fromArray($response->getData(), $response->getStatusCode());
    }

    public function getCsid(): ProductionCsid
    {
        return $this->csid;
    }

    private function submit(string $path, SignedInvoice $invoice, bool $clearanceStatus): HttpResponse
    {
        return $this->transport->send(
            'POST',
            $path,
            ['Clearance-Status' => $clearanceStatus ? '1' : '0'],
            $invoice->toPayload(),
            $this->csid,
        );
    }

    /**
     * @param list<int> $resultStatuses statuses that carry a result besides HTTP 400 with validation results
     */
    private static function isResult(HttpResponse $response, array $resultStatuses): bool
    {
        if ($response->isStatus(400)) {
            return ValidationResults::existIn($response->getData());
        }

        if (!$response->isStatus(...$resultStatuses)) {
            return false;
        }

        // 303 may come without a body; every other result must carry a JSON object
        if (!$response->hasData() && !$response->isStatus(303)) {
            throw UnexpectedResponseException::invalidBody($response, 'the body is not a JSON object');
        }

        return true;
    }
}
