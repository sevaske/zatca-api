<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Api;

use Sevaske\ZatcaApi\Credentials\ComplianceCsid;
use Sevaske\ZatcaApi\Credentials\ProductionCsid;
use Sevaske\ZatcaApi\Exception\ApiException;
use Sevaske\ZatcaApi\Exception\BadRequestException;
use Sevaske\ZatcaApi\Exception\InvalidArgumentException;
use Sevaske\ZatcaApi\Exception\TransportException;
use Sevaske\ZatcaApi\Exception\UnexpectedResponseException;
use Sevaske\ZatcaApi\Http\HttpResponse;
use Sevaske\ZatcaApi\Http\HttpTransport;
use Sevaske\ZatcaApi\Response\CsidResponse;
use Sevaske\ZatcaApi\Support\Csr;
use Sevaske\ZatcaApi\Support\Otp;

/**
 * CSID endpoints: onboarding of an EGS unit and renewal of its production CSID.
 *
 *     $compliance = $zatca->onboarding()->requestComplianceCsid($csrPem, $otp)->toComplianceCsid();
 *     // ... pass the compliance checks with $zatca->compliance($compliance) ...
 *     $production = $zatca->onboarding()->requestProductionCsid($compliance)->toProductionCsid();
 */
final class Onboarding
{
    private const COMPLIANCE_CSID_PATH = '/compliance';

    private const PRODUCTION_CSID_PATH = '/production/csids';

    private HttpTransport $transport;

    /**
     * @internal use {@see \Sevaske\ZatcaApi\Client::onboarding()}
     */
    public function __construct(HttpTransport $transport)
    {
        $this->transport = $transport;
    }

    /**
     * Issues a compliance CSID for a CSR (POST /compliance).
     *
     * @param string $csr PEM-encoded certificate signing request (or base64 of the PEM text)
     * @param string $otp one-time password from the Fatoora portal (sandbox: "123345")
     *
     * @throws InvalidArgumentException before the request, for an invalid CSR or OTP
     * @throws BadRequestException      when ZATCA rejects the CSR or OTP, see getErrors()
     * @throws TransportException
     * @throws ApiException
     */
    public function requestComplianceCsid(string $csr, string $otp): CsidResponse
    {
        $response = $this->transport->send(
            'POST',
            self::COMPLIANCE_CSID_PATH,
            ['OTP' => Otp::normalize($otp)],
            ['csr' => Csr::encode($csr)],
        );

        return self::toCsidResponse($response, [200]);
    }

    /**
     * Issues the production CSID once the compliance checks are passed (POST /production/csids).
     *
     * @throws BadRequestException e.g. "Missing-ComplianceSteps" when the compliance checks are not complete
     * @throws TransportException
     * @throws ApiException
     */
    public function requestProductionCsid(ComplianceCsid $complianceCsid): CsidResponse
    {
        $response = $this->transport->send(
            'POST',
            self::PRODUCTION_CSID_PATH,
            [],
            ['compliance_request_id' => $complianceCsid->getRequestId()],
            $complianceCsid,
        );

        return self::toCsidResponse($response, [200]);
    }

    /**
     * Renews a production CSID with a new CSR (PATCH /production/csids), authorized by the current one.
     *
     * Check {@see CsidResponse::isNotCompliant()}: with HTTP 428 ZATCA issues a compliance CSID instead,
     * the compliance checks must be passed again and then {@see requestProductionCsid()} called.
     *
     * @param string $csr new PEM-encoded certificate signing request (or base64 of the PEM text)
     * @param string $otp renewal one-time password from the Fatoora portal (sandbox: "123456")
     *
     * @throws InvalidArgumentException before the request, for an invalid CSR or OTP
     * @throws BadRequestException
     * @throws TransportException
     * @throws ApiException
     */
    public function renewProductionCsid(ProductionCsid $currentCsid, string $csr, string $otp): CsidResponse
    {
        $response = $this->transport->send(
            'PATCH',
            self::PRODUCTION_CSID_PATH,
            ['OTP' => Otp::normalize($otp)],
            ['csr' => Csr::encode($csr)],
            $currentCsid,
        );

        return self::toCsidResponse($response, [200, 428]);
    }

    /**
     * @param list<int> $resultStatuses
     */
    private static function toCsidResponse(HttpResponse $response, array $resultStatuses): CsidResponse
    {
        if (!$response->isStatus(...$resultStatuses)) {
            throw ApiException::fromHttpResponse($response);
        }

        if (!$response->hasData()) {
            throw UnexpectedResponseException::invalidBody($response, 'the body is not a JSON object');
        }

        return CsidResponse::fromArray($response->getData(), $response->getStatusCode());
    }
}
