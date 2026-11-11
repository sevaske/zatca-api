<?php

namespace Sevaske\ZatcaApi\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Sevaske\ZatcaApi\Api;
use Sevaske\ZatcaApi\AuthToken;
use Sevaske\ZatcaApi\Exceptions\ZatcaRequestException;

final class ApiTest extends TestCase
{
    private const CERTIFICATE = 'certificate';

    private const SECRET = 'secret';

    /** @var array<int, array{request: RequestInterface}> */
    private array $history = [];

    private function api(array $queue, bool $withCredentials = true): Api
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($queue));
        $stack->push(Middleware::history($this->history));

        return new Api(
            'sandbox',
            new Client(['handler' => $stack]),
            $withCredentials ? self::CERTIFICATE : null,
            $withCredentials ? self::SECRET : null,
        );
    }

    private function json(int $status, array $data): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], (string) json_encode($data));
    }

    private function lastRequest(): RequestInterface
    {
        return $this->history[array_key_last($this->history)]['request'];
    }

    private function validation(array $errors = [], array $extra = []): array
    {
        return array_merge([
            'validationResults' => [
                'infoMessages' => [],
                'warningMessages' => [],
                'errorMessages' => $errors,
                'status' => $errors ? 'ERROR' : 'PASS',
            ],
        ], $extra);
    }

    public function test_reporting_request_matches_the_spec(): void
    {
        $api = $this->api([$this->json(200, $this->validation([], ['reportingStatus' => 'REPORTED']))]);

        $response = $api->reporting('<xml/>', 'hash', 'uuid');
        $request = $this->lastRequest();

        $this->assertTrue($response->success());
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame(
            'https://gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal/invoices/reporting/single',
            (string) $request->getUri(),
        );
        $this->assertSame('V2', $request->getHeaderLine('Accept-Version'));
        $this->assertSame('1', $request->getHeaderLine('Clearance-Status'));
        $this->assertSame((new AuthToken(self::CERTIFICATE, self::SECRET))->toBasic(), $request->getHeaderLine('Authorization'));
        $this->assertSame(
            ['invoice' => base64_encode('<xml/>'), 'invoiceHash' => 'hash', 'uuid' => 'uuid'],
            json_decode((string) $request->getBody(), true),
        );
    }

    public function test_renewal_uses_patch_with_authorization_and_otp(): void
    {
        $api = $this->api([$this->json(200, [
            'requestID' => 347,
            'tokenType' => 'x509',
            'dispositionMessage' => 'ISSUED',
            'binarySecurityToken' => base64_encode('new certificate'),
            'secret' => 'new secret',
        ])]);

        $response = $api->renewProductionCertificate('csr', '123456');
        $request = $this->lastRequest();

        $this->assertSame('PATCH', $request->getMethod());
        $this->assertStringEndsWith('/production/csids', (string) $request->getUri());
        $this->assertSame('123456', $request->getHeaderLine('OTP'));
        $this->assertSame((new AuthToken(self::CERTIFICATE, self::SECRET))->toBasic(), $request->getHeaderLine('Authorization'));
        $this->assertSame(['csr' => base64_encode('csr')], json_decode((string) $request->getBody(), true));
        $this->assertTrue($response->success());
        $this->assertFalse($response->isNotCompliant());
        $this->assertSame('new certificate', $response->certificate());
        $this->assertSame('new secret', $response->secret());
    }

    public function test_renewal_requires_credentials(): void
    {
        $this->expectException(ZatcaRequestException::class);

        $this->api([], false)->renewProductionCertificate('csr', '123456');
    }

    public function test_renewal_returns_the_not_compliant_response_on_428(): void
    {
        $api = $this->api([$this->json(428, [
            'requestID' => 1234567890123,
            'tokenType' => null,
            'dispositionMessage' => 'NOT_COMPLIANT',
            'binarySecurityToken' => base64_encode('compliance certificate'),
            'secret' => 'compliance secret',
            'errors' => null,
        ])]);

        $response = $api->renewProductionCertificate('csr', '123456');

        $this->assertFalse($response->success());
        $this->assertTrue($response->isNotCompliant());
        $this->assertSame(1234567890123, $response->requestId());
        $this->assertSame('compliance certificate', $response->certificate());
    }

    public function test_rejected_invoice_exception_contains_the_zatca_errors(): void
    {
        $errors = [[
            'type' => 'ERROR',
            'code' => 'BR-KSA-37',
            'category' => 'KSA',
            'message' => 'The seller address building number must contain 4 digits.',
            'status' => 'ERROR',
        ]];
        $api = $this->api([$this->json(400, $this->validation($errors, ['reportingStatus' => 'NOT_REPORTED']))]);

        try {
            $api->reporting('<xml/>', 'hash', 'uuid');
            $this->fail('ZatcaRequestException was not thrown.');
        } catch (ZatcaRequestException $e) {
            $context = $e->context();

            $this->assertSame(400, $e->getCode());
            $this->assertSame(400, $context['status']);
            $this->assertSame($errors, $context['errors']);
            $this->assertSame('NOT_REPORTED', $context['response']['reportingStatus']);
            $this->assertJson($context['content']);
            $this->assertStringEndsWith('/invoices/reporting/single', $context['uri']);
            $this->assertSame('POST', $context['method']);
        }
    }

    public function test_certificate_error_exception_contains_the_zatca_errors(): void
    {
        $errors = [['code' => 'Invalid-OTP', 'message' => 'The provided OTP is invalid']];
        $api = $this->api([$this->json(400, ['errors' => $errors])], false);

        try {
            $api->complianceCertificate('csr', '111111');
            $this->fail('ZatcaRequestException was not thrown.');
        } catch (ZatcaRequestException $e) {
            $this->assertSame($errors, $e->context()['errors']);
            $this->assertFalse($this->lastRequest()->hasHeader('Authorization'));
        }
    }

    public function test_non_json_error_keeps_the_raw_content(): void
    {
        $api = $this->api([new Response(406, [], 'This Version is not supported or not provided in the header.')]);

        try {
            $api->clearance('<xml/>', 'hash', 'uuid');
            $this->fail('ZatcaRequestException was not thrown.');
        } catch (ZatcaRequestException $e) {
            $context = $e->context();

            $this->assertSame(406, $context['status']);
            $this->assertSame('This Version is not supported or not provided in the header.', $context['content']);
            $this->assertArrayNotHasKey('response', $context);
            $this->assertArrayNotHasKey('errors', $context);
        }
    }

    public function test_connection_error_has_no_response_context(): void
    {
        $api = $this->api([new ConnectException('No connection', new Request('POST', 'test'))]);

        try {
            $api->reporting('<xml/>', 'hash', 'uuid');
            $this->fail('ZatcaRequestException was not thrown.');
        } catch (ZatcaRequestException $e) {
            $this->assertSame('No connection', $e->getMessage());
            $this->assertSame(['uri', 'method'], array_keys($e->context()));
        }
    }

    public function test_clearance_303_is_not_followed_as_a_redirect(): void
    {
        $api = $this->api([
            new Response(
                303,
                ['Content-Type' => 'application/json', 'Location' => 'https://example.com/elsewhere'],
                (string) json_encode(['message' => 'Clearance is deactiviated.']),
            ),
        ]);

        $response = $api->clearance('<xml/>', 'hash', 'uuid');

        $this->assertCount(1, $this->history);
        $this->assertFalse($response->success());
        $this->assertSame('Clearance is deactiviated.', $response['message']);
    }

    public function test_pure_psr18_client_is_supported(): void
    {
        $client = new class($this->json(200, $this->validation([], ['clearanceStatus' => 'CLEARED']))) implements ClientInterface
        {
            public ?RequestInterface $request = null;

            public function __construct(private ResponseInterface $response) {}

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->request = $request;

                return $this->response;
            }
        };

        $api = new Api('simulation', $client, self::CERTIFICATE, self::SECRET);
        $response = $api->clearance('<xml/>', 'hash', 'uuid');

        $this->assertTrue($response->success());
        $this->assertNotNull($client->request);
        $this->assertSame('POST', $client->request->getMethod());
        $this->assertSame(
            'https://gw-fatoora.zatca.gov.sa/e-invoicing/simulation/invoices/clearance/single',
            (string) $client->request->getUri(),
        );
        $this->assertSame('application/json', $client->request->getHeaderLine('Content-Type'));
        $this->assertSame('1', $client->request->getHeaderLine('Clearance-Status'));
        $this->assertTrue($client->request->hasHeader('Authorization'));
        $this->assertSame('hash', json_decode((string) $client->request->getBody(), true)['invoiceHash']);
    }

    public function test_credentials_are_hidden_from_debug_output(): void
    {
        $api = $this->api([]);

        $dump = print_r($api, true).print_r(new AuthToken(self::CERTIFICATE, 'top-secret-value'), true);

        $this->assertStringNotContainsString('top-secret-value', $dump);
        $this->assertStringNotContainsString(self::SECRET.'"', var_export($api->__debugInfo(), true));
        $this->assertSame('[redacted]', $api->__debugInfo()['secret']);
    }

    public function test_response_without_validation_results_does_not_emit_warnings(): void
    {
        // e.g. http_errors disabled: 401 comes back as a regular response
        $api = new Api('sandbox', new Client([
            'handler' => HandlerStack::create(new MockHandler([
                $this->json(401, ['timestamp' => 1654514661409, 'status' => 401, 'error' => 'Unauthorized', 'message' => '']),
            ])),
            'http_errors' => false,
        ]), self::CERTIFICATE, self::SECRET);

        $response = $api->reporting('<xml/>', 'hash', 'uuid');

        $this->assertTrue($response->unauthorized());
        $this->assertFalse($response->success());
        $this->assertNull($response->validationStatus());
        $this->assertSame([], $response->errors());
        $this->assertSame([], $response->warnings());
        $this->assertSame([], $response->info());
    }
}
