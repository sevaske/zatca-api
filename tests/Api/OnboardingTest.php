<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Tests\Api;

use Nyholm\Psr7\Response;
use Sevaske\ZatcaApi\Exception\BadRequestException;
use Sevaske\ZatcaApi\Exception\InvalidArgumentException;
use Sevaske\ZatcaApi\Exception\UnexpectedResponseException;
use Sevaske\ZatcaApi\Response\CsidResponse;

final class OnboardingTest extends ApiTestCase
{
    private const TOKEN_TYPE = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-x509-token-profile-1.0#X509v3';

    public function testRequestComplianceCsidSendsTheSpecifiedRequest(): void
    {
        $this->http->addResponse(self::json(200, [
            'requestID' => 1234567890123,
            'dispositionMessage' => 'ISSUED',
            'binarySecurityToken' => base64_encode('certificate'),
            'secret' => 'secret',
        ]));

        $response = $this->client->onboarding()->requestComplianceCsid(self::csrPem(), '123345');

        $request = $this->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://gw-fatoora.zatca.gov.sa/e-invoicing/simulation/compliance', (string) $request->getUri());
        self::assertSame('123345', $request->getHeaderLine('OTP'));
        self::assertSame('V2', $request->getHeaderLine('Accept-Version'));
        self::assertSame('en', $request->getHeaderLine('Accept-Language'));
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertStringStartsWith('sevaske-zatca-api-php/', $request->getHeaderLine('User-Agent'));
        self::assertFalse($request->hasHeader('Authorization'));
        self::assertSame(['csr' => base64_encode(self::csrPem())], $this->lastRequestBody());

        self::assertTrue($response->isIssued());
        self::assertSame('1234567890123', $response->getRequestId());

        $csid = $response->toComplianceCsid();
        self::assertSame('certificate', $csid->getCertificate());
        self::assertSame('secret', $csid->getSecret());
        self::assertSame('1234567890123', $csid->getRequestId());
    }

    public function testAlreadyEncodedCsrIsSentAsIs(): void
    {
        $this->http->addResponse(self::json(200, ['requestID' => 1, 'dispositionMessage' => 'ISSUED', 'binarySecurityToken' => 'dGVzdA==', 'secret' => 's']));

        $this->client->onboarding()->requestComplianceCsid(base64_encode(self::csrPem()), '123345');

        self::assertSame(['csr' => base64_encode(self::csrPem())], $this->lastRequestBody());
    }

    public function testInvalidOtpIsABadRequestWithZatcaErrors(): void
    {
        $this->http->addResponse(self::json(400, ['errors' => [['code' => 'Invalid-OTP', 'message' => 'The provided OTP is invalid']]]));

        try {
            $this->client->onboarding()->requestComplianceCsid(self::csrPem(), '111111');
            self::fail('BadRequestException expected');
        } catch (BadRequestException $e) {
            self::assertSame(400, $e->getStatusCode());
            self::assertSame('ZATCA API error (HTTP 400): The provided OTP is invalid [Invalid-OTP]', $e->getMessage());
            self::assertCount(1, $e->getErrors());
            self::assertSame('Invalid-OTP', $e->getErrors()[0]->getCode());

            $request = $e->getRequest();
            self::assertNotNull($request);
            self::assertSame('[REDACTED]', $request->getHeaderLine('OTP'));
        }
    }

    public function testInvalidInputFailsBeforeAnyRequest(): void
    {
        try {
            $this->client->onboarding()->requestComplianceCsid('not a csr', '123345');
            self::fail('InvalidArgumentException expected');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('CSR', $e->getMessage());
        }

        try {
            $this->client->onboarding()->requestComplianceCsid(self::csrPem(), '12a345');
            self::fail('InvalidArgumentException expected');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('OTP', $e->getMessage());
        }

        self::assertCount(0, $this->http->getRequests());
    }

    public function testRequestProductionCsidIsAuthorizedByTheComplianceCsid(): void
    {
        $this->http->addResponse(self::json(200, [
            'requestID' => 30368,
            'tokenType' => self::TOKEN_TYPE,
            'dispositionMessage' => 'ISSUED',
            'binarySecurityToken' => base64_encode('production-certificate'),
            'secret' => 'production-secret',
        ]));

        $complianceCsid = self::complianceCsid();
        $response = $this->client->onboarding()->requestProductionCsid($complianceCsid);

        $request = $this->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertStringEndsWith('/production/csids', (string) $request->getUri());
        self::assertSame($complianceCsid->getAuthorizationHeader(), $request->getHeaderLine('Authorization'));
        self::assertFalse($request->hasHeader('OTP'));
        self::assertSame(['compliance_request_id' => '1234567890123'], $this->lastRequestBody());

        self::assertSame(self::TOKEN_TYPE, $response->getTokenType());
        self::assertSame('production-certificate', $response->toProductionCsid()->getCertificate());
    }

    public function testMissingComplianceStepsIsABadRequest(): void
    {
        $this->http->addResponse(self::json(400, ['errors' => [['code' => 'Missing-ComplianceSteps', 'message' => 'Compliance steps for this CSID are not yet complete']]]));

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('[Missing-ComplianceSteps]');

        $this->client->onboarding()->requestProductionCsid(self::complianceCsid());
    }

    public function testRenewProductionCsidSendsThePatchRequest(): void
    {
        $this->http->addResponse(self::json(200, [
            'requestID' => 347,
            'tokenType' => self::TOKEN_TYPE,
            'dispositionMessage' => 'ISSUED',
            'binarySecurityToken' => base64_encode('renewed-certificate'),
            'secret' => 'renewed-secret',
        ]));

        $current = self::productionCsid();
        $response = $this->client->onboarding()->renewProductionCsid($current, self::csrPem(), '123456');

        $request = $this->lastRequest();
        self::assertSame('PATCH', $request->getMethod());
        self::assertStringEndsWith('/production/csids', (string) $request->getUri());
        self::assertSame($current->getAuthorizationHeader(), $request->getHeaderLine('Authorization'));
        self::assertSame('123456', $request->getHeaderLine('OTP'));
        self::assertSame(['csr' => base64_encode(self::csrPem())], $this->lastRequestBody());

        self::assertTrue($response->isIssued());
        self::assertFalse($response->isNotCompliant());
        self::assertSame('renewed-certificate', $response->toProductionCsid()->getCertificate());
    }

    public function testRenewalNotCompliantReturnsAComplianceCsid(): void
    {
        $this->http->addResponse(self::json(428, [
            'requestID' => 1234567890123,
            'tokenType' => null,
            'dispositionMessage' => 'NOT_COMPLIANT',
            'binarySecurityToken' => base64_encode('compliance-certificate'),
            'secret' => 'secret',
            'errors' => null,
        ]));

        $response = $this->client->onboarding()->renewProductionCsid(self::productionCsid(), self::csrPem(), '123456');

        self::assertSame(428, $response->getHttpStatusCode());
        self::assertSame(CsidResponse::DISPOSITION_NOT_COMPLIANT, $response->getDispositionMessage());
        self::assertTrue($response->isNotCompliant());
        self::assertFalse($response->isIssued());
        self::assertSame('1234567890123', $response->toComplianceCsid()->getRequestId());
    }

    public function testNonJsonSuccessIsAnUnexpectedResponse(): void
    {
        $this->http->addResponse(new Response(200, [], 'OK'));

        $this->expectException(UnexpectedResponseException::class);

        $this->client->onboarding()->requestComplianceCsid(self::csrPem(), '123345');
    }
}
