<?php

namespace Sevaske\ZatcaApi;

use Psr\Http\Client\ClientInterface;
use Sevaske\ZatcaApi\Enums\ZatcaEndpointEnum;
use Sevaske\ZatcaApi\Enums\ZatcaEnvironmentEnum;
use Sevaske\ZatcaApi\Exceptions\ZatcaException;
use Sevaske\ZatcaApi\Exceptions\ZatcaRequestException;
use Sevaske\ZatcaApi\Exceptions\ZatcaResponseException;
use Sevaske\ZatcaApi\Responses\CertificateResponse;
use Sevaske\ZatcaApi\Responses\ClearanceResponse;
use Sevaske\ZatcaApi\Responses\ComplianceCertificateResponse;
use Sevaske\ZatcaApi\Responses\ComplianceResponse;
use Sevaske\ZatcaApi\Responses\ProductionCertificateResponse;
use Sevaske\ZatcaApi\Responses\RenewalProductionCertificateResponse;
use Sevaske\ZatcaApi\Responses\ReportingResponse;
use Sevaske\ZatcaApi\Traits\RequestBuilder;

class Api
{
    use RequestBuilder;

    protected ZatcaEnvironmentEnum $environment;

    private ?string $certificate = null;

    private ?string $secret = null;

    /**
     * Initialize the API request with an HTTP client.
     *
     * @param  ZatcaEnvironmentEnum|string  $environment  The environment to make requests (production|emulation|sandbox).
     * @param  ClientInterface  $httpClient  The HTTP client for sending requests.
     * @param  ?string  $certificate  The certificate for auth.
     * @param  ?string  $secret  The secret of the certificate for auth.
     */
    public function __construct(
        ZatcaEnvironmentEnum|string $environment,
        ClientInterface $httpClient,
        #[\SensitiveParameter] ?string $certificate = null,
        #[\SensitiveParameter] ?string $secret = null,
    ) {
        if (is_string($environment)) {
            $environment = ZatcaEnvironmentEnum::from($environment);
        }

        $this->environment = $environment;
        $this->baseUrl = $environment->url();
        $this->httpClient = $httpClient;

        $this->setCredentials($certificate, $secret);
    }

    /**
     * @throws ZatcaRequestException|ZatcaException
     */
    public function reporting(string $signedInvoice, ?string $invoiceHash, string $uuid, bool $clearanceStatus = true): ReportingResponse
    {
        $rawResponse = $this->request(
            endpoint: ZatcaEndpointEnum::Reporting->value,
            payload: [
                'invoice' => base64_encode($signedInvoice),
                'invoiceHash' => $this->normalizeInvoiceHash($invoiceHash),
                'uuid' => $uuid,
            ],
            headers: [
                'Clearance-Status' => $clearanceStatus ? 1 : 0,
            ],
            authToken: true,
            method: 'POST',
        );
        $response = new ReportingResponse($rawResponse);

        if ($response->errors()) {
            throw new ZatcaRequestException('Request failed.', [
                'errors' => $response->errors(),
            ]);
        }

        return $response;
    }

    /**
     * @throws ZatcaException
     * @throws ZatcaRequestException
     */
    public function clearance(string $signedInvoice, ?string $invoiceHash, string $uuid, bool $clearanceStatus = true): ClearanceResponse
    {
        $rawResponse = $this->request(
            endpoint: ZatcaEndpointEnum::Clearance->value,
            payload: [
                'invoice' => base64_encode($signedInvoice),
                'invoiceHash' => $this->normalizeInvoiceHash($invoiceHash),
                'uuid' => $uuid,
            ],
            headers: [
                'Clearance-Status' => $clearanceStatus ? 1 : 0,
            ],
            authToken: true,
            method: 'POST',
        );
        $response = new ClearanceResponse($rawResponse);

        if ($response->errors()) {
            throw new ZatcaRequestException('Request failed.', [
                'errors' => $response->errors(),
            ]);
        }

        return $response;
    }

    /**
     * Compliance Invoice
     *
     * @throws ZatcaException
     * @throws ZatcaRequestException
     */
    public function compliance(string $signedInvoice, ?string $invoiceHash, string $uuid): ComplianceResponse
    {
        $rawResponse = $this->request(
            endpoint: ZatcaEndpointEnum::Compliance->value,
            payload: [
                'invoice' => base64_encode($signedInvoice),
                'invoiceHash' => $this->normalizeInvoiceHash($invoiceHash),
                'uuid' => $uuid,
            ],
            authToken: true,
            method: 'POST',
        );

        $response = new ComplianceResponse($rawResponse);

        if ($response->errors()) {
            throw new ZatcaRequestException('Request failed.', [
                'errors' => $response->errors(),
            ]);
        }

        return $response;
    }

    /**
     * @throws ZatcaException
     * @throws ZatcaResponseException
     * @throws ZatcaRequestException
     */
    public function complianceCertificate(string $csr, #[\SensitiveParameter] string $otp): CertificateResponse
    {
        $rawResponse = $this->request(
            endpoint: ZatcaEndpointEnum::ComplianceCertificate->value,
            payload: ['csr' => base64_encode($csr)],
            headers: ['OTP' => $otp],
            authToken: false,
        );
        $response = new ComplianceCertificateResponse($rawResponse);

        if ($response->errors()) {
            throw new ZatcaRequestException('Request failed.', [
                'errors' => $response->errors(),
            ]);
        }

        return $response;
    }

    /**
     * @throws ZatcaException
     * @throws ZatcaResponseException
     * @throws ZatcaRequestException
     */
    public function productionCertificate(string $complianceRequestId): ProductionCertificateResponse
    {
        $rawResponse = $this->request(
            endpoint: ZatcaEndpointEnum::ProductionCertificate->value,
            payload: ['compliance_request_id' => $complianceRequestId],
            authToken: true,
        );

        $response = new ProductionCertificateResponse($rawResponse);

        if ($response->errors()) {
            throw new ZatcaRequestException('Request failed.', [
                'errors' => $response->errors(),
            ]);
        }

        return $response;
    }

    /**
     * Renews the production certificate. The current production certificate must be set as credentials.
     *
     * @throws ZatcaException
     * @throws ZatcaResponseException
     * @throws ZatcaRequestException
     */
    public function renewProductionCertificate(string $csr, #[\SensitiveParameter] string $otp): RenewalProductionCertificateResponse
    {
        // PATCH /production/csids, authorized by the current production certificate.
        // HTTP 428 (NOT_COMPLIANT) carries a compliance certificate, so it is returned as a response.
        $rawResponse = $this->request(
            endpoint: ZatcaEndpointEnum::ProductionCertificate->value,
            payload: ['csr' => base64_encode($csr)],
            headers: ['OTP' => $otp],
            authToken: true,
            method: 'PATCH',
            expectedStatuses: [428],
        );

        $response = new RenewalProductionCertificateResponse($rawResponse);

        if ($response->errors()) {
            throw new ZatcaRequestException('Request failed.', [
                'errors' => $response->errors(),
            ]);
        }

        return $response;
    }

    /**
     * Hides the credentials from var_dump() and similar debug output.
     */
    public function __debugInfo(): array
    {
        return [
            'environment' => $this->environment->value,
            'baseUrl' => $this->baseUrl,
            'certificate' => $this->certificate === null ? null : '[redacted]',
            'secret' => $this->secret === null ? null : '[redacted]',
        ];
    }

    private function normalizeInvoiceHash(?string $invoiceHash): string
    {
        return $invoiceHash ?: base64_encode('0');
    }
}
