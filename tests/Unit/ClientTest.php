<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Tests\Unit;

use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Sevaske\ZatcaApi\Client;
use Sevaske\ZatcaApi\ClientOptions;
use Sevaske\ZatcaApi\Environment;
use Sevaske\ZatcaApi\Exception\InvalidArgumentException;

final class ClientTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public function environmentProvider(): array
    {
        return [
            'sandbox' => [Environment::SANDBOX, 'https://gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal/compliance'],
            'simulation' => [Environment::SIMULATION, 'https://gw-fatoora.zatca.gov.sa/e-invoicing/simulation/compliance'],
            'production' => [Environment::PRODUCTION, 'https://gw-fatoora.zatca.gov.sa/e-invoicing/core/compliance'],
        ];
    }

    /**
     * @dataProvider environmentProvider
     */
    public function testEnvironmentSelectsTheBaseUri(string $environment, string $expectedUri): void
    {
        [$client, $http] = self::client($environment);

        $client->onboarding()->requestComplianceCsid(self::csr(), '123345');

        self::assertSame($expectedUri, (string) self::lastRequest($http)->getUri());
        self::assertSame($environment, $client->getEnvironment());
    }

    public function testNamedConstructors(): void
    {
        $options = ClientOptions::create()->withHttpClient(new MockClient());

        self::assertSame(Environment::SANDBOX, Client::sandbox($options)->getEnvironment());
        self::assertSame(Environment::SIMULATION, Client::simulation($options)->getEnvironment());
        self::assertSame(Environment::PRODUCTION, Client::production($options)->getEnvironment());
    }

    public function testUnknownEnvironmentIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown ZATCA environment "staging"');

        new Client('staging');
    }

    public function testOptionsAreAppliedToRequests(): void
    {
        [$client, $http] = self::client(Environment::SANDBOX, ClientOptions::create()
            ->withLanguage('AR')
            ->withBaseUri('https://proxy.example.com/zatca/')
            ->withUserAgent('MyErp/2.0'));

        $client->onboarding()->requestComplianceCsid(self::csr(), '123345');

        $request = self::lastRequest($http);
        self::assertSame('https://proxy.example.com/zatca/compliance', (string) $request->getUri());
        self::assertSame('ar', $request->getHeaderLine('Accept-Language'));
        self::assertSame('MyErp/2.0', $request->getHeaderLine('User-Agent'));
    }

    public function testOptionsAreImmutable(): void
    {
        $options = ClientOptions::create();
        $changed = $options->withLanguage('ar')->withClearanceEnabled(false);

        self::assertSame('en', $options->getLanguage());
        self::assertTrue($options->isClearanceEnabled());
        self::assertSame('ar', $changed->getLanguage());
        self::assertFalse($changed->isClearanceEnabled());
    }

    public function testInvalidOptionsAreRejected(): void
    {
        try {
            ClientOptions::create()->withLanguage('fr');
            self::fail('InvalidArgumentException expected');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('"fr"', $e->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        ClientOptions::create()->withBaseUri('ftp://example.com');
    }

    /**
     * @return array{Client, MockClient}
     */
    private static function client(string $environment, ?ClientOptions $options = null): array
    {
        $factory = new Psr17Factory();
        $http = new MockClient($factory);
        $http->addResponse(new Response(200, [], '{"requestID":1,"dispositionMessage":"ISSUED","binarySecurityToken":"Y2VydA==","secret":"s"}'));

        $client = new Client($environment, ($options ?? ClientOptions::create())
            ->withHttpClient($http)
            ->withRequestFactory($factory)
            ->withStreamFactory($factory));

        return [$client, $http];
    }

    private static function lastRequest(MockClient $http): RequestInterface
    {
        $request = $http->getLastRequest();
        self::assertInstanceOf(RequestInterface::class, $request);

        return $request;
    }

    private static function csr(): string
    {
        return "-----BEGIN CERTIFICATE REQUEST-----\nMIIB\n-----END CERTIFICATE REQUEST-----";
    }
}
