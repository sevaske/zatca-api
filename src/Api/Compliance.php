<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Api;

use Sevaske\ZatcaApi\Credentials\ComplianceCsid;
use Sevaske\ZatcaApi\Exception\ApiException;
use Sevaske\ZatcaApi\Exception\TransportException;
use Sevaske\ZatcaApi\Exception\UnexpectedResponseException;
use Sevaske\ZatcaApi\Http\HttpTransport;
use Sevaske\ZatcaApi\Invoice\SignedInvoice;
use Sevaske\ZatcaApi\Response\ComplianceCheckResult;
use Sevaske\ZatcaApi\Response\ValidationResults;

/**
 * Compliance checks performed with a compliance CSID during onboarding.
 *
 * Submit one sample of every document type the EGS unit will issue (standard / simplified
 * invoice, credit note and debit note), then request the production CSID.
 */
final class Compliance
{
    private const CHECK_INVOICE_PATH = '/compliance/invoices';

    private HttpTransport $transport;

    private ComplianceCsid $csid;

    /**
     * @internal use {@see \Sevaske\ZatcaApi\Client::compliance()}
     */
    public function __construct(HttpTransport $transport, ComplianceCsid $csid)
    {
        $this->transport = $transport;
        $this->csid = $csid;
    }

    /**
     * Checks a signed sample document (POST /compliance/invoices).
     *
     * A failed check is returned as a result: see {@see ComplianceCheckResult::isPassed()}.
     *
     * @throws TransportException
     * @throws ApiException e.g. AuthenticationException for wrong credentials
     */
    public function checkInvoice(SignedInvoice $invoice): ComplianceCheckResult
    {
        $response = $this->transport->send('POST', self::CHECK_INVOICE_PATH, [], $invoice->toPayload(), $this->csid);

        $isResult = $response->isStatus(200, 202)
            || ($response->isStatus(400) && ValidationResults::existIn($response->getData()));

        if (!$isResult) {
            throw ApiException::fromHttpResponse($response);
        }

        if (!$response->hasData()) {
            throw UnexpectedResponseException::invalidBody($response, 'the body is not a JSON object');
        }

        return ComplianceCheckResult::fromArray($response->getData(), $response->getStatusCode());
    }

    public function getCsid(): ComplianceCsid
    {
        return $this->csid;
    }
}
