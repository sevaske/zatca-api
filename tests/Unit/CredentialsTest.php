<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Sevaske\ZatcaApi\Credentials\ComplianceCsid;
use Sevaske\ZatcaApi\Credentials\ProductionCsid;
use Sevaske\ZatcaApi\Exception\InvalidArgumentException;
use Sevaske\ZatcaApi\Response\CsidResponse;

final class CredentialsTest extends TestCase
{
    public function testAuthorizationHeaderUsesTheTokenAsReturnedByZatca(): void
    {
        $token = base64_encode('MIIBcertificate');
        $csid = new ProductionCsid($token, 'secret');

        self::assertSame('Basic ' . base64_encode($token . ':secret'), $csid->getAuthorizationHeader());
        self::assertSame('MIIBcertificate', $csid->getCertificate());
        self::assertSame("-----BEGIN CERTIFICATE-----\nMIIBcertificate\n-----END CERTIFICATE-----", $csid->getCertificatePem());
    }

    public function testCredentialsCanBeSavedAndRestored(): void
    {
        $compliance = new ComplianceCsid(base64_encode('cert'), 'secret', '1234567890123');
        $production = new ProductionCsid(base64_encode('cert'), 'secret');

        self::assertEquals($compliance, ComplianceCsid::fromArray($compliance->toArray()));
        self::assertEquals($production, ProductionCsid::fromArray($production->toArray()));
        self::assertSame('1234567890123', ComplianceCsid::fromArray(['binarySecurityToken' => 'Y2VydA==', 'secret' => 's', 'requestId' => 1234567890123])->getRequestId());
    }

    public function testSecretIsHiddenFromDebugOutput(): void
    {
        $output = print_r(new ProductionCsid(base64_encode('cert'), 'top-secret'), true);

        self::assertStringNotContainsString('top-secret', $output);
        self::assertStringContainsString('[REDACTED]', $output);

        $response = CsidResponse::fromArray(['binarySecurityToken' => 'Y2VydA==', 'secret' => 'top-secret']);
        self::assertStringNotContainsString('top-secret', print_r($response, true));
    }

    /**
     * @return array<string, array{array<string, string>, string}>
     */
    public function invalidCredentialsProvider(): array
    {
        return [
            'empty token' => [['binarySecurityToken' => '', 'secret' => 's', 'requestId' => '1'], 'binarySecurityToken'],
            'token not base64' => [['binarySecurityToken' => 'not base64!', 'secret' => 's', 'requestId' => '1'], 'binarySecurityToken'],
            'empty secret' => [['binarySecurityToken' => 'Y2VydA==', 'secret' => ' ', 'requestId' => '1'], 'secret'],
            'missing request id' => [['binarySecurityToken' => 'Y2VydA==', 'secret' => 's'], 'requestId'],
        ];
    }

    /**
     * @dataProvider invalidCredentialsProvider
     *
     * @param array<string, string> $data
     */
    public function testInvalidCredentialsAreRejected(array $data, string $expectedMessage): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        ComplianceCsid::fromArray($data);
    }
}
