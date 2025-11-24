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
use Sevaske\ZatcaApi\Requests\ClearanceInvoiceRequest;
use Sevaske\ZatcaApi\Requests\ComplianceCertificateRequest;
use Sevaske\ZatcaApi\Requests\ComplianceInvoiceRequest;
use Sevaske\ZatcaApi\Requests\ReportingInvoiceRequest;
use Sevaske\ZatcaApi\Responses\ClearanceInvoiceResponse;
use Sevaske\ZatcaApi\Responses\ComplianceCertificateResponse;
use Sevaske\ZatcaApi\Responses\ComplianceInvoiceResponse;
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

    private ZatcaEnvironment $environment;

    /**
     * @param string|ZatcaEndpoint $environment
     * @param ClientInterface $client
     * @param RequestFactoryInterface $requestFactory
     * @param StreamFactoryInterface $streamFactory
     */
    public function __construct(
        $environment,
        ClientInterface $client,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory
    )
    {
        if ($environment instanceof ZatcaEnvironment) {
            $this->environment = $environment;
        } else {
            $this->environment = new ZatcaEnvironment((string) $environment);
        }

        $this->client = $client;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
    }

    public function environment(): ZatcaEnvironment
    {
        return $this->environment;
    }

    /**
     * @throws ZatcaResponseException
     * @throws ZatcaRequestException
     */
    public function clearanceInvoice(string $invoice, ?string $invoiceHash, string $uuid): ClearanceInvoiceResponse
    {
        $request = $this->buildRequest(new ClearanceInvoiceRequest($invoice, $invoiceHash, $uuid));

        return new ClearanceInvoiceResponse($this->sendRequest($request));
    }

    /**
     * @throws ZatcaResponseException
     * @throws ZatcaRequestException
     */
    public function reportingInvoice(string $invoice, ?string $invoiceHash, string $uuid): ReportingInvoiceResponse
    {
        $request = $this->buildRequest(new ReportingInvoiceRequest($invoice, $invoiceHash, $uuid));

        return new ReportingInvoiceResponse($this->sendRequest($request));
    }

    /**
     * @throws ZatcaException
     * @throws ZatcaRequestException
     */
    public function complianceInvoice(string $invoice, ?string $invoiceHash, string $uuid): ComplianceInvoiceResponse
    {
        $request = $this->buildRequest(new ComplianceInvoiceRequest($invoice, $invoiceHash, $uuid));

        return new ComplianceInvoiceResponse($this->sendRequest($request));
    }

    /**
     * @throws ZatcaRequestException
     * @throws ZatcaResponseException
     */
    public function complianceCertification(string $csr, string $otp): ComplianceCertificateResponse
    {
        $request = $this->buildRequest(new ComplianceCertificateRequest($csr, $otp));

        return new ComplianceCertificateResponse($this->sendRequest($request));
    }

    /**
     * Build a PSR-7 request from a ZatcaRequestInterface instance.
     *
     * Adds an Authorization header if the request requires an auth token.
     *
     * @param ZatcaRequestInterface $request
     * @return RequestInterface
     * @throws ZatcaRequestException
     */
    private function buildRequest(ZatcaRequestInterface $request): RequestInterface
    {
        $options = $request->options();

        // if the request requires an auth token and Authorization header is not already set
        if (($request instanceof RequiresAuthTokenInterface) && ! isset($options['headers']['Authorization'])) {
            if (! $this->authToken instanceof AuthTokenInterface) {
                throw new ZatcaRequestException('Auth token required.', [
                        'uri' => $request->uri(),
                        'options' => $request->options(),
                ]);
            }

            $options['headers']['Authorization'] = 'Basic ' . $this->authToken->token();
        }

        return $this->prepareRequest($request->method(), $request->uri(), $options);
    }

    /**
     * @param RequestInterface $request
     *
     * @return ResponseInterface
     * @throws ZatcaRequestException
     */
    private function sendRequest(RequestInterface $request): ResponseInterface
    {
        try {
            return (new \Sevaske\ZatcaApi\Middleware\Pipeline())
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
}