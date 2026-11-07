<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Tests\Api;

use Http\Client\Exception\NetworkException;
use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use Sevaske\ZatcaApi\ClientOptions;
use Sevaske\ZatcaApi\Contracts\InvoiceSubmitter;
use Sevaske\ZatcaApi\Exception\BadRequestException;
use Sevaske\ZatcaApi\Exception\NotAcceptableException;
use Sevaske\ZatcaApi\Exception\ServerException;
use Sevaske\ZatcaApi\Exception\TransportException;
use Sevaske\ZatcaApi\Exception\UnexpectedResponseException;
use Sevaske\ZatcaApi\Response\ClearanceResult;
use Sevaske\ZatcaApi\Response\ReportingResult;

final class InvoicesTest extends ApiTestCase
{
    public function testInvoicesImplementTheSubmitterContract(): void
    {
        self::assertInstanceOf(InvoiceSubmitter::class, $this->client->invoices(self::productionCsid()));
    }

    public function testReportSendsTheSpecifiedRequest(): void
    {
        $this->http->addResponse(self::json(200, ['validationResults' => self::validationResults('PASS'), 'reportingStatus' => 'REPORTED']));

        $csid = self::productionCsid();
        $invoice = self::signedInvoice();
        $result = $this->client->invoices($csid)->report($invoice);

        $request = $this->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://gw-fatoora.zatca.gov.sa/e-invoicing/simulation/invoices/reporting/single', (string) $request->getUri());
        self::assertSame($csid->getAuthorizationHeader(), $request->getHeaderLine('Authorization'));
        self::assertSame('1', $request->getHeaderLine('Clearance-Status'));
        self::assertSame('V2', $request->getHeaderLine('Accept-Version'));
        self::assertSame($invoice->toPayload(), $this->lastRequestBody());

        self::assertTrue($result->isReported());
        self::assertSame(ReportingResult::STATUS_REPORTED, $result->getReportingStatus());
        self::assertTrue($result->getValidationResults()->isPassed());
    }

    public function testReportWithClearanceDisabledSendsClearanceStatusZero(): void
    {
        $client = $this->createClient(ClientOptions::create()->withClearanceEnabled(false));
        $this->http->addResponse(self::json(200, ['validationResults' => self::validationResults('PASS'), 'reportingStatus' => 'REPORTED']));

        $client->invoices(self::productionCsid())->report(self::signedInvoice());

        self::assertSame('0', $this->lastRequest()->getHeaderLine('Clearance-Status'));
    }

    public function testReportOutcomesAreResults(): void
    {
        $warning = ['type' => 'WARNING', 'code' => 'BR-KSA-98', 'category' => 'KSA', 'message' => 'Submit within 24 hours', 'status' => 'WARNING'];
        $error = ['type' => 'ERROR', 'code' => 'BR-KSA-06', 'category' => 'KSA', 'message' => 'Invalid transaction code', 'status' => 'ERROR'];
        $duplicate = ['type' => 'ERROR', 'code' => null, 'category' => null, 'message' => 'Invoice was already Reported successfully earlier.', 'status' => 'ERROR'];

        $this->http->addResponse(self::json(202, ['validationResults' => self::validationResults('WARNING', [], [$warning]), 'reportingStatus' => 'REPORTED']));
        $this->http->addResponse(self::json(400, ['validationResults' => self::validationResults('ERROR', [$error]), 'reportingStatus' => 'NOT_REPORTED']));
        $this->http->addResponse(self::json(409, ['validationResults' => self::validationResults('ERROR', [$duplicate]), 'reportingStatus' => 'NOT_REPORTED']));
        $this->http->addResponse(new Response(303));

        $invoices = $this->client->invoices(self::productionCsid());

        $accepted = $invoices->report(self::signedInvoice());
        self::assertTrue($accepted->isReported());
        self::assertTrue($accepted->hasWarnings());

        $rejected = $invoices->report(self::signedInvoice());
        self::assertFalse($rejected->isReported());
        self::assertSame(['BR-KSA-06'], $rejected->getValidationResults()->getErrorCodes());
        self::assertSame('[BR-KSA-06] Invalid transaction code', (string) $rejected->getValidationResults()->getErrorMessages()[0]);

        $alreadyReported = $invoices->report(self::signedInvoice());
        self::assertTrue($alreadyReported->isAlreadyReported());
        self::assertFalse($alreadyReported->isReported());

        $clearanceRequired = $invoices->report(self::signedInvoice());
        self::assertTrue($clearanceRequired->isClearanceRequired());
        self::assertNull($clearanceRequired->getReportingStatus());
    }

    public function testClearSendsTheSpecifiedRequestAndReturnsTheClearedInvoice(): void
    {
        $client = $this->createClient(ClientOptions::create()->withClearanceEnabled(false));
        $this->http->addResponse(self::json(200, [
            'validationResults' => self::validationResults('PASS'),
            'clearanceStatus' => 'CLEARED',
            'clearedInvoice' => base64_encode('<Invoice>cleared</Invoice>'),
        ]));

        $result = $client->invoices(self::productionCsid())->clear(self::signedInvoice());

        $request = $this->lastRequest();
        self::assertStringEndsWith('/invoices/clearance/single', (string) $request->getUri());
        self::assertSame('1', $request->getHeaderLine('Clearance-Status'));

        self::assertTrue($result->isCleared());
        self::assertSame(ClearanceResult::STATUS_CLEARED, $result->getClearanceStatus());
        self::assertSame('<Invoice>cleared</Invoice>', $result->getClearedInvoice());
        self::assertSame(base64_encode('<Invoice>cleared</Invoice>'), $result->getClearedInvoiceBase64());
    }

    public function testClearOutcomesAreResults(): void
    {
        $cleared = ['validationResults' => self::validationResults('PASS'), 'clearanceStatus' => 'CLEARED', 'clearedInvoice' => base64_encode('<Invoice/>')];
        $this->http->addResponse(self::json(208, $cleared));
        $this->http->addResponse(self::json(303, ['message' => 'Clearance is deactiviated. Please use the /invoices/reporting/single endpoint instead.']));
        $this->http->addResponse(self::json(400, [
            'validationResults' => self::validationResults('ERROR', [['type' => 'ERROR', 'code' => 'BR-KSA-14', 'category' => 'KSA', 'message' => 'Buyer identification', 'status' => 'ERROR']]),
            'clearanceStatus' => 'NOT_CLEARED',
            'clearedInvoice' => null,
        ]));

        $invoices = $this->client->invoices(self::productionCsid());

        $alreadyCleared = $invoices->clear(self::signedInvoice());
        self::assertTrue($alreadyCleared->isAlreadyCleared());
        self::assertTrue($alreadyCleared->isCleared());
        self::assertSame('<Invoice/>', $alreadyCleared->getClearedInvoice());

        $disabled = $invoices->clear(self::signedInvoice());
        self::assertTrue($disabled->isClearanceDisabled());
        self::assertFalse($disabled->isCleared());
        self::assertStringContainsString('reporting', (string) $disabled->getMessage());

        $rejected = $invoices->clear(self::signedInvoice());
        self::assertFalse($rejected->isCleared());
        self::assertNull($rejected->getClearedInvoice());
        self::assertSame(['BR-KSA-14'], $rejected->getValidationResults()->getErrorCodes());
    }

    public function testBadRequestWithoutValidationResultsThrows(): void
    {
        $this->http->addResponse(self::json(400, ['code' => 'Invalid-Request', 'message' => 'Malformed request']));

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Malformed request [Invalid-Request]');

        $this->client->invoices(self::productionCsid())->report(self::signedInvoice());
    }

    public function testServerErrorThrows(): void
    {
        $this->http->addResponse(self::json(500, ['category' => 'HTTP-Errors', 'code' => '500', 'message' => 'Something went wrong and caused an Internal Server Error.']));

        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('ZATCA API error (HTTP 500): Something went wrong');

        $this->client->invoices(self::productionCsid())->clear(self::signedInvoice());
    }

    public function testNotAcceptableThrows(): void
    {
        $this->http->addResponse(new Response(406, ['Content-Type' => 'text/plain'], 'This Version is not supported or not provided in the header.'));

        $this->expectException(NotAcceptableException::class);

        $this->client->invoices(self::productionCsid())->report(self::signedInvoice());
    }

    public function testNonJsonSuccessIsAnUnexpectedResponse(): void
    {
        $this->http->addResponse(new Response(200, [], ''));

        $this->expectException(UnexpectedResponseException::class);

        $this->client->invoices(self::productionCsid())->report(self::signedInvoice());
    }

    public function testNetworkErrorIsATransportExceptionWithRedactedCredentials(): void
    {
        $this->http->addException(new NetworkException('Connection timed out', new Request('POST', 'https://example.com')));

        try {
            $this->client->invoices(self::productionCsid())->report(self::signedInvoice());
            self::fail('TransportException expected');
        } catch (TransportException $e) {
            self::assertStringContainsString('Connection timed out', $e->getMessage());
            self::assertSame('[REDACTED]', $e->getRequest()->getHeaderLine('Authorization'));
        }
    }
}
