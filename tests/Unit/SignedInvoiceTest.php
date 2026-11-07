<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Sevaske\ZatcaApi\Exception\InvalidArgumentException;
use Sevaske\ZatcaApi\Invoice\SignedInvoice;

final class SignedInvoiceTest extends TestCase
{
    /**
     * Hash returned by the php-zatca-xml signer for the fixture (accepted by the ZATCA sandbox).
     */
    private const FIXTURE_HASH = 'JDXm4w8lD0YRVX06qGEvWvd7EPr4Kalg2QcpCB0qKgI=';

    private const FIXTURE_UUID = '3cf5ee18-ee25-44ea-a444-2c37ba7f28be';

    private static function fixture(): string
    {
        $xml = file_get_contents(__DIR__ . '/../Fixtures/simplified-invoice-signed.xml');
        self::assertIsString($xml);

        return $xml;
    }

    public function testFromXmlReadsTheUuidAndComputesTheZatcaHash(): void
    {
        $invoice = SignedInvoice::fromXml(self::fixture());

        self::assertSame(self::FIXTURE_UUID, $invoice->getUuid());
        self::assertSame(self::FIXTURE_HASH, $invoice->getHash());
        self::assertSame(self::FIXTURE_HASH, SignedInvoice::computeHash(self::fixture()));
    }

    public function testHashIgnoresSignatureAndQrCode(): void
    {
        $xml = self::fixture();
        $withoutSignature = preg_replace('#<ext:UBLExtensions>.*</ext:UBLExtensions>#s', '<ext:UBLExtensions><ext:UBLExtension/></ext:UBLExtensions>', $xml);
        self::assertIsString($withoutSignature);

        self::assertSame(self::FIXTURE_HASH, SignedInvoice::computeHash($withoutSignature));
    }

    public function testHashChangesWithTheInvoiceContent(): void
    {
        $changed = str_replace('SME00010', 'SME00011', self::fixture());

        self::assertNotSame(self::FIXTURE_HASH, SignedInvoice::computeHash($changed));
    }

    public function testToPayloadMatchesTheApiRequestBody(): void
    {
        $invoice = new SignedInvoice(self::fixture(), self::FIXTURE_HASH, self::FIXTURE_UUID);

        self::assertSame([
            'invoiceHash' => self::FIXTURE_HASH,
            'uuid' => self::FIXTURE_UUID,
            'invoice' => base64_encode(self::fixture()),
        ], $invoice->toPayload());
    }

    /**
     * @return array<string, array{string, string, string, string}>
     */
    public function invalidArgumentsProvider(): array
    {
        $xml = '<Invoice/>';

        return [
            'empty xml' => [' ', self::FIXTURE_HASH, self::FIXTURE_UUID, 'XML'],
            'hash not base64' => [$xml, 'not base64!', self::FIXTURE_UUID, 'hash'],
            'hash not sha256' => [$xml, base64_encode('short'), self::FIXTURE_UUID, 'hash'],
            'invalid uuid' => [$xml, self::FIXTURE_HASH, 'SME00010', 'UUID'],
        ];
    }

    /**
     * @dataProvider invalidArgumentsProvider
     */
    public function testConstructorValidatesItsArguments(string $xml, string $hash, string $uuid, string $expectedMessage): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        new SignedInvoice($xml, $hash, $uuid);
    }

    public function testMalformedXmlIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not well-formed');

        SignedInvoice::fromXml('<Invoice><unclosed></Invoice>');
    }

    public function testXmlWithoutUuidIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cbc:UUID');

        SignedInvoice::fromXml('<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"/>');
    }
}
