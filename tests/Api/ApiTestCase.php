<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Tests\Api;

use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Sevaske\ZatcaApi\Client;
use Sevaske\ZatcaApi\ClientOptions;
use Sevaske\ZatcaApi\Credentials\ComplianceCsid;
use Sevaske\ZatcaApi\Credentials\ProductionCsid;
use Sevaske\ZatcaApi\Environment;
use Sevaske\ZatcaApi\Invoice\SignedInvoice;

abstract class ApiTestCase extends TestCase
{
    protected MockClient $http;

    protected Client $client;

    protected function setUp(): void
    {
        $this->http = new MockClient(new Psr17Factory());
        $this->client = $this->createClient();
    }

    protected function createClient(?ClientOptions $options = null, string $environment = Environment::SIMULATION): Client
    {
        $factory = new Psr17Factory();

        return new Client($environment, ($options ?? ClientOptions::create())
            ->withHttpClient($this->http)
            ->withRequestFactory($factory)
            ->withStreamFactory($factory));
    }

    /**
     * @param array<array-key, mixed> $body
     */
    protected static function json(int $status, array $body): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($body, JSON_THROW_ON_ERROR));
    }

    protected function lastRequest(): RequestInterface
    {
        $request = $this->http->getLastRequest();
        self::assertInstanceOf(RequestInterface::class, $request);

        return $request;
    }

    /**
     * @return array<array-key, mixed>
     */
    protected function lastRequestBody(): array
    {
        $body = json_decode((string) $this->lastRequest()->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($body);

        return $body;
    }

    protected static function complianceCsid(): ComplianceCsid
    {
        return new ComplianceCsid(base64_encode('compliance-certificate'), 'compliance-secret', '1234567890123');
    }

    protected static function productionCsid(): ProductionCsid
    {
        return new ProductionCsid(base64_encode('production-certificate'), 'production-secret');
    }

    protected static function signedInvoice(): SignedInvoice
    {
        $xml = file_get_contents(__DIR__ . '/../Fixtures/simplified-invoice-signed.xml');
        self::assertIsString($xml);

        return SignedInvoice::fromXml($xml);
    }

    protected static function csrPem(): string
    {
        return "-----BEGIN CERTIFICATE REQUEST-----\nMIIBTESTCSR\n-----END CERTIFICATE REQUEST-----";
    }

    /**
     * @param list<array<string, string|null>> $errors
     * @param list<array<string, string|null>> $warnings
     *
     * @return array<string, mixed>
     */
    protected static function validationResults(string $status, array $errors = [], array $warnings = []): array
    {
        return [
            'infoMessages' => [[
                'type' => 'INFO',
                'code' => 'XSD_ZATCA_VALID',
                'category' => 'XSD validation',
                'message' => 'Complied with UBL 2.1 standards in line with ZATCA specifications',
                'status' => 'PASS',
            ]],
            'warningMessages' => $warnings,
            'errorMessages' => $errors,
            'status' => $status,
        ];
    }
}
