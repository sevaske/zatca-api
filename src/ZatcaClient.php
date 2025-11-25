<?php
declare(strict_types=1);

namespace Sevaske\ZatcaApi;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Sevaske\ZatcaApi\Exceptions\ZatcaException;
use Sevaske\ZatcaApi\Exceptions\ZatcaRequestException;
use Sevaske\ZatcaApi\Exceptions\ZatcaResponseException;
use Sevaske\ZatcaApi\Interfaces\AuthTokenInterface;
use Sevaske\ZatcaApi\Interfaces\RequestInterface as ZatcaRequestInterface;
use Sevaske\ZatcaApi\Interfaces\RequiresAuthTokenInterface;
use Sevaske\ZatcaApi\Interfaces\ZatcaEnvironmentInterface;
use Sevaske\ZatcaApi\Middleware\Pipeline;
use Sevaske\ZatcaApi\Requests\ClearanceInvoiceRequest;
use Sevaske\ZatcaApi\Requests\ComplianceCertificateRequest;
use Sevaske\ZatcaApi\Requests\ComplianceInvoiceRequest;
use Sevaske\ZatcaApi\Requests\ProductionCertificateRequest;
use Sevaske\ZatcaApi\Requests\ReportingInvoiceRequest;
use Sevaske\ZatcaApi\Responses\ClearanceInvoiceResponse;
use Sevaske\ZatcaApi\Responses\ComplianceCertificateResponse;
use Sevaske\ZatcaApi\Responses\ComplianceInvoiceResponse;
use Sevaske\ZatcaApi\Responses\ProductionCertificateResponse;
use Sevaske\ZatcaApi\Responses\ReportingInvoiceResponse;
use Sevaske\ZatcaApi\Traits\HasAuthToken;
use Sevaske\ZatcaApi\Traits\HasMiddleware;
use Sevaske\ZatcaApi\Traits\Http;
use Throwable;

class ZatcaClient
{
    use Http;
    use HasAuthToken;
    use HasMiddleware;

    protected ZatcaEnvironmentInterface $environment;

    /**
     * @param ClientInterface $client PSR-18 HTTP client
     * @param RequestFactoryInterface $requestFactory PSR-17 request factory
     * @param StreamFactoryInterface $streamFactory PSR-17 stream factory
     * @param string|ZatcaEndpoint $environment
     */
    public function __construct(
        ClientInterface $client,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        $environment = 'sandbox'
    )
    {
        $this->client = $client;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
        $this->environment = $this->resolveEnvironment($environment);
    }

    /**
     * Returns a new client with a different environment.
     * Auth token is reset to prevent token leakage.
     *
     * @param string|ZatcaEnvironmentInterface $environment New environment
     * @return static Immutable clone with updated environment
     */
    public function withEnvironment($environment)
    {
        $clone = clone $this;
        $clone->resolveEnvironment($environment);
        // reset token
        $clone->authToken = null;

        return $clone;
    }

    /**
     * Returns the current environment instance.
     *
     * @return ZatcaEnvironmentInterface
     */
    public function environment(): ZatcaEnvironmentInterface
    {
        return $this->environment;
    }

    /**
     * Clearance invoice (B2B).
     *
     * @param string $invoice JSON/XML invoice content
     * @param string $invoiceHash Invoice hash
     * @param string $uuid Unique request identifier
     * @return ClearanceInvoiceResponse
     * @throws ZatcaResponseException On invalid response
     * @throws ZatcaRequestException On request error
     */
    public function clearanceInvoice(string $invoice, string $invoiceHash, string $uuid): ClearanceInvoiceResponse
    {
        $request = $this->buildRequest(new ClearanceInvoiceRequest($invoice, $invoiceHash, $uuid));

        return new ClearanceInvoiceResponse($this->sendRequest($request));
    }

    /**
     * Reporting invoice (B2C).
     *
     * @param string $invoice JSON/XML invoice content
     * @param string $invoiceHash Invoice hash
     * @param string $uuid Unique request identifier
     * @return ReportingInvoiceResponse
     * @throws ZatcaResponseException On invalid response
     * @throws ZatcaRequestException On request error
     */
    public function reportingInvoice(string $invoice, string $invoiceHash, string $uuid): ReportingInvoiceResponse
    {
        $request = $this->buildRequest(new ReportingInvoiceRequest($invoice, $invoiceHash, $uuid));

        return new ReportingInvoiceResponse($this->sendRequest($request));
    }

    /**
     * Compliance invoice.
     *
     * @param string $invoice JSON/XML invoice content
     * @param string $invoiceHash Invoice hash
     * @param string $uuid Unique request identifier
     * @return ComplianceInvoiceResponse
     * @throws ZatcaResponseException On invalid response
     * @throws ZatcaRequestException On request error
     */
    public function complianceInvoice(string $invoice, string $invoiceHash, string $uuid): ComplianceInvoiceResponse
    {
        $request = $this->buildRequest(new ComplianceInvoiceRequest($invoice, $invoiceHash, $uuid));

        return new ComplianceInvoiceResponse($this->sendRequest($request));
    }

    /**
     * Request a compliance certificate.
     *
     * @param string $csr Certificate signing request
     * @param string $otp One-time password for validation
     * @return ComplianceCertificateResponse
     * @throws ZatcaRequestException
     * @throws ZatcaResponseException
     */
    public function complianceCertification(string $csr, string $otp): ComplianceCertificateResponse
    {
        $request = $this->buildRequest(new ComplianceCertificateRequest($csr, $otp));

        return new ComplianceCertificateResponse($this->sendRequest($request));
    }

    /**
     * Request a production certificate based on a compliance request ID.
     *
     * @param string $complianceRequestId ID returned from compliance request
     * @return ProductionCertificateResponse
     * @throws ZatcaResponseException
     * @throws ZatcaRequestException
     */
    public function productionCertificate(string $complianceRequestId): ProductionCertificateResponse
    {
        $request = $this->buildRequest(new ProductionCertificateRequest($complianceRequestId));

        return new ProductionCertificateResponse($this->sendRequest($request));
    }

    /**
     * Build a PSR-7 request from a ZatcaRequestInterface instance.
     * Adds authorization header if required by the request.
     *
     * @param ZatcaRequestInterface $request Request abstraction
     * @return RequestInterface Prepared PSR-7 request
     * @throws ZatcaRequestException When auth token is missing
     */
    protected function buildRequest(ZatcaRequestInterface $request): RequestInterface
    {
        $url = $this->environment->url($request->uri());
        $options = $request->options();

        // if the request requires an auth token and Authorization header is not already set
        if (($request instanceof RequiresAuthTokenInterface) && ! isset($options['headers']['Authorization'])) {
            $options = $this->attachAuthHeader($options);
        }

        return $this->prepareRequest($request->method(), $url, $options);
    }

    /**
     * Send the PSR-7 request via the middleware pipeline.
     * Catches HTTP and client exceptions and wraps them with context.
     *
     * @param RequestInterface $request
     * @return ResponseInterface PSR-7 response
     * @throws ZatcaRequestException On transport or client errors
     */
    protected function sendRequest(RequestInterface $request): ResponseInterface
    {
        try {
            return (new Pipeline())
                ->send($request)
                ->through($this->middleware)
                ->then(fn($req) => $this->client->sendRequest($req));
        } catch (ClientExceptionInterface|Throwable $e) {
            throw (new ZatcaRequestException($e->getMessage(), [],$e->getCode(), $e))
                ->withContext([
                    'uri' => $request->getUri(),
                    'body' => $request->getBody(),
                    'method' => $request->getMethod(),
                    'headers' => $request->getHeaders(),
                ]);
        }
    }


    /**
     * @throws ZatcaRequestException
     */
    protected function attachAuthHeader(array $options): array
    {
        if (! $this->authToken instanceof AuthTokenInterface) {
            throw new ZatcaRequestException('Auth token is required.');
        }

        $options['headers']['Authorization'] = 'Basic ' . $this->authToken->token();

        return $options;
    }

    /**
     * Normalize or resolve environment input to a ZatcaEnvironmentInterface.
     *
     * @param string|ZatcaEnvironmentInterface $environment
     * @return ZatcaEnvironmentInterface
     */
    protected function resolveEnvironment($environment): ZatcaEnvironmentInterface
    {
        if ($environment instanceof ZatcaEnvironmentInterface) {
            return $environment;
        }

        return new ZatcaEnvironment((string) $environment);
    }
}