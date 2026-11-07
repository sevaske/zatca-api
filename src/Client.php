<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi;

use Http\Discovery\Exception as DiscoveryException;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Sevaske\ZatcaApi\Api\Compliance;
use Sevaske\ZatcaApi\Api\Invoices;
use Sevaske\ZatcaApi\Api\Onboarding;
use Sevaske\ZatcaApi\Credentials\ComplianceCsid;
use Sevaske\ZatcaApi\Credentials\ProductionCsid;
use Sevaske\ZatcaApi\Exception\ConfigurationException;
use Sevaske\ZatcaApi\Exception\InvalidArgumentException;
use Sevaske\ZatcaApi\Http\HttpTransport;

/**
 * Entry point of the ZATCA e-invoicing API client.
 *
 *     $zatca = Client::simulation();
 *
 *     $complianceCsid = $zatca->onboarding()->requestComplianceCsid($csrPem, $otp)->toComplianceCsid();
 *     $zatca->compliance($complianceCsid)->checkInvoice(SignedInvoice::fromXml($sampleXml));
 *     $productionCsid = $zatca->onboarding()->requestProductionCsid($complianceCsid)->toProductionCsid();
 *
 *     $result = $zatca->invoices($productionCsid)->report(SignedInvoice::fromXml($signedXml));
 */
final class Client
{
    public const VERSION = '3.0.0';

    private string $environment;

    private ClientOptions $options;

    private HttpTransport $transport;

    private ?Onboarding $onboarding = null;

    /**
     * @param string             $environment one of the {@see Environment} constants
     * @param ClientOptions|null $options     optional HTTP client, language, clearance status, ...
     *
     * @throws InvalidArgumentException for an unknown environment
     * @throws ConfigurationException   when no PSR-18 client / PSR-17 factory is available
     */
    public function __construct(string $environment = Environment::SANDBOX, ?ClientOptions $options = null)
    {
        $baseUri = Environment::baseUri($environment);

        $this->environment = $environment;
        $this->options = $options ?? new ClientOptions();

        $this->transport = new HttpTransport(
            $this->resolveHttpClient(),
            $this->resolveRequestFactory(),
            $this->resolveStreamFactory(),
            $this->options->getBaseUri() ?? $baseUri,
            $this->options->getLanguage(),
            $this->resolveUserAgent(),
        );
    }

    /**
     * Developer portal (Integration Sandbox).
     */
    public static function sandbox(?ClientOptions $options = null): self
    {
        return new self(Environment::SANDBOX, $options);
    }

    public static function simulation(?ClientOptions $options = null): self
    {
        return new self(Environment::SIMULATION, $options);
    }

    public static function production(?ClientOptions $options = null): self
    {
        return new self(Environment::PRODUCTION, $options);
    }

    /**
     * CSID endpoints: compliance CSID, production CSID, renewal.
     */
    public function onboarding(): Onboarding
    {
        if ($this->onboarding === null) {
            $this->onboarding = new Onboarding($this->transport);
        }

        return $this->onboarding;
    }

    /**
     * Compliance checks authorized by a compliance CSID.
     */
    public function compliance(ComplianceCsid $csid): Compliance
    {
        return new Compliance($this->transport, $csid);
    }

    /**
     * Reporting and clearance authorized by a production CSID.
     */
    public function invoices(ProductionCsid $csid): Invoices
    {
        return new Invoices($this->transport, $csid, $this->options->isClearanceEnabled());
    }

    public function getEnvironment(): string
    {
        return $this->environment;
    }

    public function getOptions(): ClientOptions
    {
        return $this->options;
    }

    private function resolveHttpClient(): ClientInterface
    {
        $httpClient = $this->options->getHttpClient();

        if ($httpClient !== null) {
            return $httpClient;
        }

        try {
            return Psr18ClientDiscovery::find();
        } catch (DiscoveryException $e) {
            throw self::discoveryFailed('PSR-18 HTTP client', $e);
        }
    }

    private function resolveRequestFactory(): RequestFactoryInterface
    {
        $requestFactory = $this->options->getRequestFactory();

        if ($requestFactory !== null) {
            return $requestFactory;
        }

        try {
            return Psr17FactoryDiscovery::findRequestFactory();
        } catch (DiscoveryException $e) {
            throw self::discoveryFailed('PSR-17 request factory', $e);
        }
    }

    private function resolveStreamFactory(): StreamFactoryInterface
    {
        $streamFactory = $this->options->getStreamFactory();

        if ($streamFactory !== null) {
            return $streamFactory;
        }

        try {
            return Psr17FactoryDiscovery::findStreamFactory();
        } catch (DiscoveryException $e) {
            throw self::discoveryFailed('PSR-17 stream factory', $e);
        }
    }

    private function resolveUserAgent(): string
    {
        return $this->options->getUserAgent()
            ?? sprintf('sevaske-zatca-api-php/%s (PHP %s)', self::VERSION, PHP_VERSION);
    }

    private static function discoveryFailed(string $missingComponent, DiscoveryException $previous): ConfigurationException
    {
        return new ConfigurationException(sprintf(
            'No %s found. Install one (e.g. "composer require guzzlehttp/guzzle") or pass it explicitly via ClientOptions.',
            $missingComponent,
        ), 0, $previous);
    }
}
