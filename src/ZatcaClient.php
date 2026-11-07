<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi;

use InvalidArgumentException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
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
use Sevaske\ZatcaApi\Requests\RenewProductionCertificateRequest;
use Sevaske\ZatcaApi\Requests\ReportingInvoiceRequest;
use Sevaske\ZatcaApi\Responses\ClearanceInvoiceResponse;
use Sevaske\ZatcaApi\Responses\ComplianceCertificateResponse;
use Sevaske\ZatcaApi\Responses\ComplianceInvoiceResponse;
use Sevaske\ZatcaApi\Responses\ProductionCertificateResponse;
use Sevaske\ZatcaApi\Responses\RenewalProductionCertificateResponse;
use Sevaske\ZatcaApi\Responses\ReportingInvoiceResponse;
use Sevaske\ZatcaApi\Traits\HasAuthToken;
use Sevaske\ZatcaApi\Traits\HasMiddleware;
use Sevaske\ZatcaApi\Traits\Http;
use Throwable;

class ZatcaClient
{
    use HasAuthToken;
    use HasMiddleware;
    use Http;

    protected ZatcaEnvironmentInterface $environment;

    /**
     * Value of the Accept-Language header; null keeps the request default ("en").
     */
    protected ?string $language = null;

    /**
     * Whether ZATCA error responses (4xx with a JSON body) are returned instead of thrown.
     */
    protected bool $returnErrorResponses = false;

    /**
     * @param  ClientInterface  $client  PSR-18 HTTP client
     * @param  RequestFactoryInterface  $requestFactory  PSR-17 request factory
     * @param  StreamFactoryInterface  $streamFactory  PSR-17 stream factory
     * @param  string|ZatcaEnvironmentInterface  $environment  sandbox | simulation | production
     */
    public function __construct(
        ClientInterface $client,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        $environment = 'sandbox'
    ) {
        $this->client = $client;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
        $this->environment = $this->normalizeEnvironment($environment);
    }

    /**
     * Returns a new client with a different environment.
     * Auth token is reset to prevent token leakage.
     *
     * @param  string|ZatcaEnvironmentInterface  $environment  New environment
     * @return static Immutable clone with updated environment
     */
    public function withEnvironment($environment)
    {
        $clone = clone $this;
        $clone->environment = $clone->normalizeEnvironment($environment);
        // reset token
        $clone->authToken = null;

        return $clone;
    }

    /**
     * Returns a new client that requests responses in the given language ("en" or "ar").
     *
     * @return static
     *
     * @throws InvalidArgumentException If the language is not supported by ZATCA
     */
    public function withLanguage(string $language)
    {
        $language = strtolower($language);

        if (! in_array($language, ['en', 'ar'], true)) {
            throw new InvalidArgumentException('Language must be "en" or "ar".');
        }

        $clone = clone $this;
        $clone->language = $language;

        return $clone;
    }

    /**
     * Returns a new client that returns ZATCA error responses (4xx with a JSON body,
     * e.g. a rejected invoice or invalid OTP) as response objects instead of throwing.
     * Use success(), errors() and statusCode() on the returned response.
     *
     * Transport errors and 5xx responses are still thrown.
     *
     * @return static
     */
    public function withErrorResponses(bool $enabled = true)
    {
        $clone = clone $this;
        $clone->returnErrorResponses = $enabled;

        return $clone;
    }

    /**
     * Returns the current environment instance.
     */
    public function environment(): ZatcaEnvironmentInterface
    {
        return $this->environment;
    }

    /**
     * Clearance invoice (B2B).
     *
     * @param  string  $invoice  JSON/XML invoice content
     * @param  string  $invoiceHash  Invoice hash
     * @param  string  $uuid  Unique request identifier
     *
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
     * @param  string  $invoice  JSON/XML invoice content
     * @param  string  $invoiceHash  Invoice hash
     * @param  string  $uuid  Unique request identifier
     * @param  bool  $clearanceStatus  "Clearance-Status" header: true ("1") when clearance is enabled, false ("0") when disabled
     *
     * @throws ZatcaResponseException On invalid response
     * @throws ZatcaRequestException On request error
     */
    public function reportingInvoice(string $invoice, string $invoiceHash, string $uuid, bool $clearanceStatus = true): ReportingInvoiceResponse
    {
        $request = $this->buildRequest(new ReportingInvoiceRequest($invoice, $invoiceHash, $uuid, $clearanceStatus));

        return new ReportingInvoiceResponse($this->sendRequest($request));
    }

    /**
     * Compliance invoice.
     *
     * @param  string  $invoice  JSON/XML invoice content
     * @param  string  $invoiceHash  Invoice hash
     * @param  string  $uuid  Unique request identifier
     *
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
     * @param  string  $csr  Certificate signing request
     * @param  string  $otp  One-time password for validation
     *
     * @throws ZatcaRequestException
     * @throws ZatcaResponseException
     */
    public function complianceCertificate(string $csr, string $otp): ComplianceCertificateResponse
    {
        $request = $this->buildRequest(new ComplianceCertificateRequest($csr, $otp));

        return new ComplianceCertificateResponse($this->sendRequest($request));
    }

    /**
     * Request a production certificate based on a compliance request ID.
     *
     * @param  int  $complianceRequestId  ID returned from compliance request
     *
     * @throws ZatcaResponseException
     * @throws ZatcaRequestException
     */
    public function productionCertificate(int $complianceRequestId): ProductionCertificateResponse
    {
        $request = $this->buildRequest(new ProductionCertificateRequest($complianceRequestId));

        return new ProductionCertificateResponse($this->sendRequest($request));
    }

    /**
     * Renew a production certificate (PATCH /production/csids).
     * Requires the current production certificate as the auth token.
     *
     * HTTP 428 (NOT_COMPLIANT) is returned as a response: ZATCA issues a compliance certificate
     * and the compliance checks must be passed again; see isNotCompliant().
     *
     * @param  string  $csr  New certificate signing request
     * @param  string  $otp  Renewal one-time password from the Fatoora portal
     *
     * @throws ZatcaRequestException
     * @throws ZatcaResponseException
     */
    public function renewProductionCertificate(string $csr, string $otp): RenewalProductionCertificateResponse
    {
        $request = $this->buildRequest(new RenewProductionCertificateRequest($csr, $otp));

        return new RenewalProductionCertificateResponse($this->dispatch($request, [428]));
    }

    /**
     * Send the PSR-7 request via the middleware pipeline.
     * Catches HTTP and client exceptions and wraps them with context.
     *
     * @return ResponseInterface PSR-7 response
     *
     * @throws ZatcaRequestException On transport or client errors
     */
    protected function sendRequest(RequestInterface $request): ResponseInterface
    {
        return $this->dispatch($request, []);
    }

    /**
     * @param  int[]  $passthroughStatuses  HTTP error statuses returned as a response instead of thrown
     *
     * @throws ZatcaRequestException On transport or client errors
     */
    private function dispatch(RequestInterface $request, array $passthroughStatuses): ResponseInterface
    {
        try {
            /**
             * @var ResponseInterface $response
             */
            $response = (new Pipeline)
                ->send($request)
                ->through($this->middleware)
                ->then(fn ($req) => $this->client->sendRequest($req));

            if ($response->getStatusCode() >= 400 && ! $this->shouldReturnErrorResponse($response, $passthroughStatuses)) {
                throw new ZatcaRequestException($response->getReasonPhrase(), [
                    'request' => $request,
                    'response' => $response,
                ]);
            }

            return $response;
        } catch (ClientExceptionInterface|Throwable $e) {
            // Safely read and sanitize request information for context
            try {
                $rawHeaders = $request->getHeaders();
                $sanitizedHeaders = [];

                foreach ($rawHeaders as $name => $values) {
                    if (strtolower($name) === 'authorization') {
                        $sanitizedHeaders[$name] = ['[REDACTED]'];

                        continue;
                    }

                    $sanitizedHeaders[$name] = $values;
                }
            } catch (Throwable $ex) {
                $sanitizedHeaders = ['[unreadable headers]'];
            }

            try {
                $stream = $request->getBody();
                if ($stream->isSeekable()) {
                    $stream->rewind();
                    $contents = $stream->getContents();
                    $stream->rewind();
                } else {
                    $contents = (string) $stream;
                }

                $body = strlen($contents) > 1024 ? substr($contents, 0, 1024).'... (truncated)' : $contents;
            } catch (Throwable $ex) {
                $body = '[unreadable body]';
            }

            $context = [
                'uri' => $request->getUri(),
                'body' => $body,
                'method' => $request->getMethod(),
                'headers' => $sanitizedHeaders,
            ];

            // HTTP error response from ZATCA: expose the status and the parsed body (e.g. validation errors)
            if ($e instanceof ZatcaRequestException && ($e->context()['response'] ?? null) instanceof ResponseInterface) {
                $response = $e->context()['response'];
                $context['status'] = $response->getStatusCode();
                $context['response'] = $response;
                $context['data'] = self::decodeJsonBody($response);
            }

            throw (new ZatcaRequestException($e->getMessage(), [], $e->getCode(), $e))
                ->withContext($context);
        }
    }

    /**
     * @param  int[]  $passthroughStatuses
     */
    private function shouldReturnErrorResponse(ResponseInterface $response, array $passthroughStatuses): bool
    {
        $status = $response->getStatusCode();

        if (in_array($status, $passthroughStatuses, true)) {
            return true;
        }

        return $this->returnErrorResponses && $status < 500 && self::decodeJsonBody($response) !== null;
    }

    /**
     * Decode a JSON object body without consuming the stream; null if the body is not a JSON object.
     */
    private static function decodeJsonBody(ResponseInterface $response): ?array
    {
        try {
            $body = $response->getBody();
            if ($body->isSeekable()) {
                $body->rewind();
            }
            $contents = $body->getContents();
            if ($body->isSeekable()) {
                $body->rewind();
            }
        } catch (Throwable $e) {
            return null;
        }

        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Build a PSR-7 request from a ZatcaRequestInterface instance.
     * Adds authorization header if required by the request.
     *
     * @param  ZatcaRequestInterface  $request  Request abstraction
     * @return RequestInterface Prepared PSR-7 request
     *
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

        if ($this->language !== null) {
            $options['headers']['Accept-Language'] = $this->language;
        }

        return $this->prepareRequest($request->method(), $url, $options);
    }

    /**
     * @throws ZatcaRequestException
     */
    protected function attachAuthHeader(array $options): array
    {
        if (! $this->authToken instanceof AuthTokenInterface) {
            throw new ZatcaRequestException('Auth token is required.');
        }

        $options['headers']['Authorization'] = 'Basic '.$this->authToken->token();

        return $options;
    }

    /**
     * Normalize environment input to a ZatcaEnvironmentInterface.
     *
     * @param  string|ZatcaEnvironmentInterface  $environment
     */
    protected function normalizeEnvironment($environment): ZatcaEnvironmentInterface
    {
        if ($environment instanceof ZatcaEnvironmentInterface) {
            return $environment;
        }

        if (! is_string($environment)) {
            throw new InvalidArgumentException('Environment must be a string or ZatcaEnvironmentInterface.');
        }

        return new ZatcaEnvironment((string) $environment);
    }
}
