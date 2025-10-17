<?php

namespace Tests;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Sevaske\ZatcaApi\Exceptions\ZatcaResponseException;
use Sevaske\ZatcaApi\Responses\ValidationResponse;

class ValidationResponseTest extends TestCase
{
    protected function makeJsonResponse(array $data, int $status = 200): ResponseInterface
    {
        return new Response(
            $status,
            ['Content-Type' => 'application/json'],
            Utils::streamFor(json_encode($data))
        );
    }

    public function test_it_parses_valid_response(): void
    {
        $response = new ValidationResponse($this->makeJsonResponse([
            'validationResults' => [
                'status' => 'SUCCESS',
                'infoMessages' => ['All good'],
                'warningMessages' => [],
                'errorMessages' => [],
            ],
        ]));

        $this->assertEquals('SUCCESS', $response->validationStatus());
        $this->assertSame(['All good'], $response->info());
        $this->assertFalse($response->hasErrors());
        $this->assertFalse($response->hasWarnings());
    }

    public function test_it_detects_errors_and_warnings(): void
    {
        $response = new ValidationResponse($this->makeJsonResponse([
            'validationResults' => [
                'status' => 'FAILED',
                'infoMessages' => [],
                'warningMessages' => ['Deprecated field'],
                'errorMessages' => ['Missing field'],
            ],
        ]));

        $this->assertTrue($response->hasErrors());
        $this->assertTrue($response->hasWarnings());
        $this->assertEquals(['Missing field'], $response->errors());
        $this->assertEquals(['Deprecated field'], $response->warnings());
    }

    public function test_it_throws_exception_on_invalid_json(): void
    {
        $this->expectException(ZatcaResponseException::class);

        $invalidJson = '{invalid json}';
        $response = new Response(200, ['Content-Type' => 'application/json'], Utils::streamFor($invalidJson));

        new ValidationResponse($response);
    }

    public function test_it_accepts_array_response(): void
    {
        $response = new ValidationResponse([
            'validationResults' => [
                'status' => 'SUCCESS',
                'infoMessages' => [],
                'warningMessages' => [],
                'errorMessages' => [],
            ],
        ]);

        $this->assertIsArray($response->validation());
        $this->assertEquals('SUCCESS', $response->validationStatus());
    }

    public function test_it_detects_unauthorized_status(): void
    {
        $response = new ValidationResponse([
            'status' => 401,
            'validationResults' => [],
        ]);

        $this->assertTrue($response->unauthorized());
    }

    public function test_raw_method_returns_original_response(): void
    {
        $arrayData = ['foo' => 'bar'];
        $response = new ValidationResponse($arrayData);

        $this->assertSame($arrayData, $response->raw());
    }
}
