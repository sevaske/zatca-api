<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Sevaske\ZatcaApi\Responses\ReportingResponse;
use Sevaske\ZatcaApi\Responses\ZatcaResponse;

class ReportingResponseTest extends TestCase
{
    /**
     * Creates a mock of the ReportingResponse class with mocked attributes.
     *
     * @param  array  $attributes  Simulated response attributes.
     */
    protected function makeResponse(array $attributes): ReportingResponse
    {
        $response = $this->getMockBuilder(ReportingResponse::class)
            ->setConstructorArgs([$attributes])
            ->onlyMethods(['getOptionalAttribute'])
            ->getMock();

        $response->method('getOptionalAttribute')
            ->willReturnCallback(function ($key) use ($attributes) {
                return $attributes[$key] ?? null;
            });

        return $response;
    }

    public function test_success_response_200(): void
    {
        $response = $this->makeResponse([
            'reportingStatus' => 'REPORTED',
            'validationResults' => [
                'infoMessages' => [
                    [
                        'type' => 'INFO',
                        'code' => 'XSD_ZATCA_VALID',
                        'category' => 'XSD validation',
                        'message' => 'Complied with UBL 2.1 standards in line with ZATCA specifications',
                        'status' => 'PASS',
                    ],
                ],
                'warningMessages' => [],
                'errorMessages' => [],
                'status' => 'PASS',
            ],
        ]);

        $this->assertTrue($response->success());
        $this->assertEquals('PASS', $response->validationStatus());
        $this->assertEmpty($response->warnings());
        $this->assertEmpty($response->errors());
        $this->assertFalse($response->hasErrors());
        $this->assertFalse($response->hasWarnings());
    }

    public function test_response_with_warnings_202(): void
    {
        $response = $this->makeResponse([
            'reportingStatus' => 'REPORTED',
            'validationResults' => [
                'infoMessages' => [
                    ['status' => 'PASS'],
                ],
                'warningMessages' => [
                    ['status' => 'WARNING'],
                    ['status' => 'WARNING'],
                ],
                'errorMessages' => [],
                'status' => 'WARNING',
            ],
        ]);

        $this->assertTrue($response->success());
        $this->assertEquals('WARNING', $response->validationStatus());
        $this->assertCount(2, $response->warnings());
        $this->assertEmpty($response->errors());
        $this->assertFalse($response->hasErrors());
        $this->assertTrue($response->hasWarnings());
    }

    public function test_response_with_errors_400(): void
    {
        $response = $this->makeResponse([
            'reportingStatus' => 'NOT_REPORTED',
            'validationResults' => [
                'infoMessages' => [
                    ['status' => 'PASS'],
                ],
                'warningMessages' => [],
                'errorMessages' => [
                    ['status' => 'ERROR'],
                    ['status' => 'ERROR'],
                ],
                'status' => 'ERROR',
            ],
        ]);

        $this->assertFalse($response->success());
        $this->assertEquals('ERROR', $response->validationStatus());
        $this->assertCount(2, $response->errors());
        $this->assertTrue($response->hasErrors());
        $this->assertFalse($response->hasWarnings());
    }

    public function test_unauthorized_response_401_should_return_null_validation(): void
    {
        $response = $this->makeResponse([
            'timestamp' => time(),
            'status' => 401,
            'error' => 'Unauthorized',
            'message' => '',
        ]);

        $this->assertTrue($response->unauthorized());
        $this->assertFalse($response->success());
    }

    public function test_already_reported_response_409(): void
    {
        $response = $this->makeResponse([
            'reportingStatus' => 'NOT_REPORTED',
            'validationResults' => [
                'infoMessages' => [],
                'warningMessages' => [],
                'errorMessages' => [
                    [
                        'message' => 'Invoice was already Reported successfully earlier.',
                        'status' => 'ERROR',
                    ],
                ],
                'status' => 'ERROR',
            ],
        ]);

        $this->assertFalse($response->success());
        $this->assertEquals('ERROR', $response->validationStatus());
        $this->assertTrue($response->hasErrors());
    }

    public function test_internal_server_error_500_response(): void
    {
        /** @var ZatcaResponse $response */
        $response = $this->makeResponse([
            'category' => 'HTTP-Errors',
            'code' => '500',
            'message' => 'Something went wrong and caused an Internal Server Error.',
        ]);

        $this->assertFalse($response->success());
        $this->assertNull($response->validationStatus());
    }
}
