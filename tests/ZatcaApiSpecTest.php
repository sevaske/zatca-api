<?php

declare(strict_types=1);

namespace Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Sevaske\ZatcaApi\Exceptions\ZatcaRequestException;
use Sevaske\ZatcaApi\ZatcaAuth;
use Sevaske\ZatcaApi\ZatcaClient;

/**
 * Checks requests and response handling against the official ZATCA Integration Sandbox swagger (release 2.1.0).
 */
final class ZatcaApiSpecTest extends TestCase
{
    /** @var array<int, array{request: RequestInterface, response: ?ResponseInterface}> */
    private array $history = [];

    private function client(array $responses, bool $withToken = true): ZatcaClient
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));
        $factory = new HttpFactory;

        $client = new ZatcaClient(new Client(['handler' => $stack, 'allow_redirects' => false]), $factory, $factory, 'simulation');

        if ($withToken) {
            $client->setAuthToken(new ZatcaAuth('certificate', 'secret'));
        }

        return $client;
    }

    private function lastRequest(): RequestInterface
    {
        return $this->history[count($this->history) - 1]['request'];
    }

    private function lastBody(): array
    {
        return json_decode((string) $this->lastRequest()->getBody(), true);
    }

    private static function json(int $status, array $body): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($body));
    }

    private static function validation(string $status, array $errors = [], array $warnings = []): array
    {
        return [
            'infoMessages' => [],
            'warningMessages' => $warnings,
            'errorMessages' => $errors,
            'status' => $status,
        ];
    }

    public function invoiceEndpointProvider(): array
    {
        return [
            'reporting' => ['reportingInvoice', '/invoices/reporting/single', '1'],
            'clearance' => ['clearanceInvoice', '/invoices/clearance/single', '1'],
            'compliance' => ['complianceInvoice', '/compliance/invoices', null],
        ];
    }

    /**
     * @dataProvider invoiceEndpointProvider
     */
    public function test_invoice_requests_match_the_spec(string $method, string $path, ?string $clearanceStatus): void
    {
        $this->client([self::json(200, [])])->{$method}('<Invoice/>', 'aGFzaA==', 'uuid-1');

        $request = $this->lastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertStringEndsWith('/e-invoicing/simulation'.$path, (string) $request->getUri());
        $this->assertSame('V2', $request->getHeaderLine('Accept-Version'));
        $this->assertStringStartsWith('Basic ', $request->getHeaderLine('Authorization'));
        $this->assertSame(['invoice' => base64_encode('<Invoice/>'), 'invoiceHash' => 'aGFzaA==', 'uuid' => 'uuid-1'], $this->lastBody());

        if ($clearanceStatus === null) {
            $this->assertFalse($request->hasHeader('Clearance-Status'));
        } else {
            $this->assertSame($clearanceStatus, $request->getHeaderLine('Clearance-Status'));
        }
    }

    public function test_reporting_clearance_status_can_be_disabled(): void
    {
        $this->client([self::json(200, [])])->reportingInvoice('<Invoice/>', 'hash', 'uuid', false);

        $this->assertSame('0', $this->lastRequest()->getHeaderLine('Clearance-Status'));
    }

    public function test_missing_auth_token_throws_request_exception(): void
    {
        $this->expectException(ZatcaRequestException::class);
        $this->expectExceptionMessage('Auth token is required.');

        $this->client([], false)->reportingInvoice('<Invoice/>', 'hash', 'uuid');
    }

    public function test_compliance_certificate_request_matches_the_spec(): void
    {
        $response = $this->client([self::json(200, [
            'requestID' => 1234567890123,
            'dispositionMessage' => 'ISSUED',
            'binarySecurityToken' => base64_encode('cert'),
            'secret' => 'secret',
        ])], false)->complianceCertificate('csr', '123345');

        $request = $this->lastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertStringEndsWith('/compliance', (string) $request->getUri());
        $this->assertSame('123345', $request->getHeaderLine('OTP'));
        $this->assertFalse($request->hasHeader('Authorization'));
        $this->assertSame(['csr' => base64_encode('csr')], $this->lastBody());

        $this->assertTrue($response->success());
        $this->assertSame('ISSUED', $response->dispositionMessage());
        $this->assertSame(1234567890123, $response->requestId());
        $this->assertSame('cert', $response->certificate());
    }

    public function test_renew_production_certificate_matches_the_spec(): void
    {
        $response = $this->client([self::json(200, [
            'requestID' => 347,
            'tokenType' => 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-x509-token-profile-1.0#X509v3',
            'dispositionMessage' => 'ISSUED',
            'binarySecurityToken' => base64_encode('new-cert'),
            'secret' => 'new-secret',
        ])])->renewProductionCertificate('new csr', '123456');

        $request = $this->lastRequest();
        $this->assertSame('PATCH', $request->getMethod());
        $this->assertStringEndsWith('/production/csids', (string) $request->getUri());
        $this->assertSame('123456', $request->getHeaderLine('OTP'));
        $this->assertSame('V2', $request->getHeaderLine('Accept-Version'));
        $this->assertStringStartsWith('Basic ', $request->getHeaderLine('Authorization'));
        $this->assertSame(['csr' => base64_encode('new csr')], $this->lastBody());

        $this->assertTrue($response->success());
        $this->assertFalse($response->isNotCompliant());
        $this->assertStringContainsString('X509v3', (string) $response->tokenType());
        $this->assertSame('new-cert', $response->certificate());
    }

    public function test_renewal_428_not_compliant_is_returned_as_response(): void
    {
        $response = $this->client([self::json(428, [
            'requestID' => 1234567890123,
            'tokenType' => null,
            'dispositionMessage' => 'NOT_COMPLIANT',
            'binarySecurityToken' => base64_encode('compliance-cert'),
            'secret' => 'secret',
            'errors' => null,
        ])])->renewProductionCertificate('csr', '123456');

        $this->assertSame(428, $response->statusCode());
        $this->assertFalse($response->success());
        $this->assertTrue($response->isNotCompliant());
        $this->assertSame('compliance-cert', $response->certificate());
    }

    public function test_rejected_invoice_exception_exposes_zatca_errors_and_keeps_old_context(): void
    {
        $error = ['type' => 'ERROR', 'code' => 'invalid-invoice-hash', 'category' => 'INVOICE_HASHING_ERRORS', 'message' => 'The invoice hash API body does not match the (calculated) Hash of the XML', 'status' => 'ERROR'];
        $client = $this->client([self::json(400, ['validationResults' => self::validation('ERROR', [$error]), 'reportingStatus' => 'NOT_REPORTED'])]);

        try {
            $client->reportingInvoice('<Invoice/>', 'hash', 'uuid');
            $this->fail('Expected ZatcaRequestException');
        } catch (ZatcaRequestException $e) {
            // unchanged behaviour
            $this->assertSame('Bad Request', $e->getMessage());
            foreach (['uri', 'body', 'method', 'headers'] as $key) {
                $this->assertArrayHasKey($key, $e->context());
            }
            $this->assertSame(['[REDACTED]'], $e->context()['headers']['Authorization']);
            $this->assertInstanceOf(ZatcaRequestException::class, $e->getPrevious());
            $this->assertInstanceOf(ResponseInterface::class, $e->getPrevious()->context()['response']);

            // new: direct access to the ZATCA response
            $this->assertSame(400, $e->statusCode());
            $this->assertSame([$error], $e->errors());
            $this->assertSame('NOT_REPORTED', $e->data()['reportingStatus']);
            $this->assertInstanceOf(ResponseInterface::class, $e->response());
        }
    }

    public function test_certificate_error_exception_exposes_errors(): void
    {
        $client = $this->client([self::json(400, ['errors' => [['code' => 'Invalid-OTP', 'message' => 'The provided OTP is invalid']]])], false);

        try {
            $client->complianceCertificate('csr', '111111');
            $this->fail('Expected ZatcaRequestException');
        } catch (ZatcaRequestException $e) {
            $this->assertSame([['code' => 'Invalid-OTP', 'message' => 'The provided OTP is invalid']], $e->errors());
        }
    }

    public function test_error_responses_mode_returns_rejected_invoice_as_response(): void
    {
        $error = ['type' => 'ERROR', 'code' => 'BR-KSA-06', 'category' => 'KSA', 'message' => 'Invalid transaction code', 'status' => 'ERROR'];
        $response = $this->client([self::json(400, ['validationResults' => self::validation('ERROR', [$error]), 'reportingStatus' => 'NOT_REPORTED'])])
            ->withErrorResponses()
            ->reportingInvoice('<Invoice/>', 'hash', 'uuid');

        $this->assertSame(400, $response->statusCode());
        $this->assertFalse($response->success());
        $this->assertSame([$error], $response->errors());
    }

    public function test_error_responses_mode_reports_409_already_reported(): void
    {
        $response = $this->client([self::json(409, [
            'validationResults' => self::validation('ERROR', [['type' => 'ERROR', 'code' => null, 'category' => null, 'message' => 'Invoice was already Reported successfully earlier.', 'status' => 'ERROR']]),
            'reportingStatus' => 'NOT_REPORTED',
        ])])->withErrorResponses()->reportingInvoice('<Invoice/>', 'hash', 'uuid');

        $this->assertTrue($response->isAlreadyReported());
        $this->assertFalse($response->success());
    }

    public function test_error_responses_mode_still_throws_on_server_errors(): void
    {
        $this->expectException(ZatcaRequestException::class);

        $this->client([self::json(500, ['category' => 'HTTP-Errors', 'code' => '500', 'message' => 'Something went wrong'])])
            ->withErrorResponses()
            ->reportingInvoice('<Invoice/>', 'hash', 'uuid');
    }

    public function test_error_responses_mode_is_off_by_default(): void
    {
        $this->expectException(ZatcaRequestException::class);

        $this->client([self::json(409, ['validationResults' => self::validation('ERROR'), 'reportingStatus' => 'NOT_REPORTED'])])
            ->reportingInvoice('<Invoice/>', 'hash', 'uuid');
    }

    public function test_reporting_202_accepted_with_warnings(): void
    {
        $warning = ['type' => 'WARNING', 'code' => 'BR-KSA-98', 'category' => 'KSA', 'message' => 'Submit within 24 hours', 'status' => 'WARNING'];
        $response = $this->client([self::json(202, ['validationResults' => self::validation('WARNING', [], [$warning]), 'reportingStatus' => 'REPORTED'])])
            ->reportingInvoice('<Invoice/>', 'hash', 'uuid');

        $this->assertSame(202, $response->statusCode());
        $this->assertTrue($response->success());
        $this->assertTrue($response->hasWarnings());
        $this->assertSame([$warning], $response->warnings());
        $this->assertSame('WARNING', $response->validationStatus());
    }

    public function test_clearance_200_and_208_return_cleared_invoice(): void
    {
        foreach ([200, 208] as $status) {
            $response = $this->client([self::json($status, [
                'validationResults' => self::validation('PASS'),
                'clearanceStatus' => 'CLEARED',
                'clearedInvoice' => base64_encode('<Invoice>cleared</Invoice>'),
            ])])->clearanceInvoice('<Invoice/>', 'hash', 'uuid');

            $this->assertSame($status, $response->statusCode());
            $this->assertTrue($response->success());
            $this->assertSame('<Invoice>cleared</Invoice>', $response->clearedInvoiceXml());
        }
    }

    public function test_clearance_303_when_clearance_is_deactivated(): void
    {
        $response = $this->client([self::json(303, ['message' => 'Clearance is deactiviated. Please use the /invoices/reporting/single endpoint instead.'])])
            ->clearanceInvoice('<Invoice/>', 'hash', 'uuid');

        $this->assertTrue($response->isClearanceDisabled());
        $this->assertFalse($response->success());
        $this->assertStringContainsString('reporting', (string) $response->message());
        $this->assertNull($response->clearedInvoiceXml());
    }

    public function test_message_accessors_tolerate_missing_and_object_shaped_lists(): void
    {
        $empty = $this->client([new Response(200, [], '')])->reportingInvoice('<Invoice/>', 'hash', 'uuid');
        $this->assertSame([], $empty->errors());
        $this->assertSame([], $empty->warnings());
        $this->assertSame([], $empty->info());

        $info = ['type' => 'INFO', 'code' => 'XSD_ZATCA_VALID', 'category' => 'XSD validation', 'message' => 'Complied', 'status' => 'PASS'];
        $partial = $this->client([self::json(200, ['validationResults' => ['infoMessages' => $info, 'status' => 'PASS'], 'reportingStatus' => 'REPORTED'])])
            ->complianceInvoice('<Invoice/>', 'hash', 'uuid');
        $this->assertSame([$info], $partial->info());
        $this->assertSame([], $partial->warnings());
        $this->assertSame([], $partial->errors());
    }

    public function test_language_can_be_switched_to_arabic(): void
    {
        $client = $this->client([self::json(200, []), self::json(200, [])]);

        $client->withLanguage('ar')->reportingInvoice('<Invoice/>', 'hash', 'uuid');
        $this->assertSame('ar', $this->lastRequest()->getHeaderLine('Accept-Language'));

        $client->reportingInvoice('<Invoice/>', 'hash', 'uuid');
        $this->assertSame('en', $this->lastRequest()->getHeaderLine('Accept-Language'));
    }

    public function test_unsupported_language_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->client([])->withLanguage('fr');
    }
}
