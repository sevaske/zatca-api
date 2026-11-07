<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Tests\Api;

use Nyholm\Psr7\Response;
use Sevaske\ZatcaApi\Exception\AuthenticationException;

final class ComplianceTest extends ApiTestCase
{
    public function testCheckInvoiceSendsTheSpecifiedRequest(): void
    {
        $this->http->addResponse(self::json(202, [
            'validationResults' => self::validationResults('WARNING', [], [[
                'type' => 'WARNING',
                'code' => 'BR-KSA-F-13',
                'category' => 'KSA',
                'message' => 'Please recheck Other Seller/Buyer ID',
                'status' => 'WARNING',
            ]]),
            'reportingStatus' => 'REPORTED',
            'clearanceStatus' => null,
            'qrSellertStatus' => null,
            'qrBuyertStatus' => null,
        ]));

        $csid = self::complianceCsid();
        $invoice = self::signedInvoice();
        $result = $this->client->compliance($csid)->checkInvoice($invoice);

        $request = $this->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertStringEndsWith('/compliance/invoices', (string) $request->getUri());
        self::assertSame($csid->getAuthorizationHeader(), $request->getHeaderLine('Authorization'));
        self::assertFalse($request->hasHeader('Clearance-Status'));
        self::assertSame($invoice->toPayload(), $this->lastRequestBody());

        self::assertSame(202, $result->getHttpStatusCode());
        self::assertTrue($result->isPassed());
        self::assertTrue($result->hasWarnings());
        self::assertSame('REPORTED', $result->getReportingStatus());
        self::assertSame('BR-KSA-F-13', $result->getValidationResults()->getWarningMessages()[0]->getCode());
    }

    public function testFailedCheckIsAResult(): void
    {
        $this->http->addResponse(self::json(400, [
            'validationResults' => self::validationResults('ERROR', [[
                'type' => 'ERROR',
                'code' => 'invalid-invoice-hash',
                'category' => 'INVOICE_HASHING_ERRORS',
                'message' => 'The invoice hash API body does not match the (calculated) Hash of the XML',
                'status' => 'ERROR',
            ]]),
            'reportingStatus' => 'NOT_REPORTED',
            'clearanceStatus' => null,
            'qrSellertStatus' => 'PASS',
            'qrBuyertStatus' => null,
        ]));

        $result = $this->client->compliance(self::complianceCsid())->checkInvoice(self::signedInvoice());

        self::assertFalse($result->isPassed());
        self::assertSame(['invalid-invoice-hash'], $result->getValidationResults()->getErrorCodes());
        self::assertSame('PASS', $result->getQrSellerStatus());
    }

    public function testInfoMessagesObjectInsteadOfListIsNormalized(): void
    {
        $info = ['type' => 'INFO', 'code' => 'XSD_ZATCA_VALID', 'category' => 'XSD validation', 'message' => 'Complied', 'status' => 'PASS'];
        $this->http->addResponse(self::json(200, [
            'validationResults' => ['infoMessages' => $info, 'status' => 'PASS'],
            'reportingStatus' => 'REPORTED',
        ]));

        $validation = $this->client->compliance(self::complianceCsid())->checkInvoice(self::signedInvoice())->getValidationResults();

        self::assertCount(1, $validation->getInfoMessages());
        self::assertSame('XSD_ZATCA_VALID', $validation->getInfoMessages()[0]->getCode());
        self::assertSame([], $validation->getWarningMessages());
        self::assertSame([], $validation->getErrorMessages());
        self::assertTrue($validation->isPassed());
    }

    public function testLegacyErrorFormatIsSupported(): void
    {
        $this->http->addResponse(self::json(400, [
            'invoiceHash' => 'hash',
            'status' => 'Not Reported',
            'warnings' => null,
            'errors' => [['category' => 'Signature-Errors', 'code' => 'Missing-Signature', 'message' => 'Please include a digital signature in the invoice']],
        ]));

        $result = $this->client->compliance(self::complianceCsid())->checkInvoice(self::signedInvoice());

        self::assertFalse($result->isPassed());
        self::assertSame('ERROR', $result->getValidationResults()->getStatus());
        self::assertSame(['Missing-Signature'], $result->getValidationResults()->getErrorCodes());
    }

    public function testWrongCredentialsThrow(): void
    {
        $this->http->addResponse(new Response(401));

        try {
            $this->client->compliance(self::complianceCsid())->checkInvoice(self::signedInvoice());
            self::fail('AuthenticationException expected');
        } catch (AuthenticationException $e) {
            self::assertSame(401, $e->getStatusCode());
            self::assertSame('ZATCA API error (HTTP 401): Unauthorized', $e->getMessage());

            $request = $e->getRequest();
            self::assertNotNull($request);
            self::assertSame('[REDACTED]', $request->getHeaderLine('Authorization'));
        }
    }
}
