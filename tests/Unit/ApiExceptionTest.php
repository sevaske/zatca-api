<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Tests\Unit;

use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Sevaske\ZatcaApi\Exception\ApiException;
use Sevaske\ZatcaApi\Exception\AuthenticationException;
use Sevaske\ZatcaApi\Exception\ForbiddenException;
use Sevaske\ZatcaApi\Exception\RateLimitException;
use Sevaske\ZatcaApi\Exception\ServerException;
use Sevaske\ZatcaApi\Exception\ZatcaException;
use Sevaske\ZatcaApi\Http\HttpResponse;

final class ApiExceptionTest extends TestCase
{
    /**
     * @return array<string, array{int, class-string<ApiException>}>
     */
    public function statusProvider(): array
    {
        return [
            'unauthorized' => [401, AuthenticationException::class],
            'forbidden' => [403, ForbiddenException::class],
            'unknown client error' => [418, ApiException::class],
            'rate limited' => [429, RateLimitException::class],
            'server error' => [503, ServerException::class],
        ];
    }

    /**
     * @dataProvider statusProvider
     *
     * @param class-string<ApiException> $expectedClass
     */
    public function testStatusCodeSelectsTheExceptionClass(int $status, string $expectedClass): void
    {
        $exception = ApiException::fromHttpResponse(self::httpResponse($status, null));

        self::assertSame($expectedClass, get_class($exception));
        self::assertInstanceOf(ZatcaException::class, $exception);
        self::assertSame($status, $exception->getStatusCode());
        self::assertSame($status, $exception->getCode());
    }

    public function testGatewayErrorBodyIsReadable(): void
    {
        $exception = ApiException::fromHttpResponse(self::httpResponse(401, [
            'timestamp' => 1654514661409,
            'status' => 401,
            'error' => 'Unauthorized',
            'message' => '',
        ]));

        self::assertSame('ZATCA API error (HTTP 401): Unauthorized', $exception->getMessage());
        self::assertCount(1, $exception->getErrors());
        self::assertSame('Unauthorized', $exception->getErrors()[0]->getMessage());
    }

    public function testValidationErrorsOfAnErrorResponseAreExposed(): void
    {
        $exception = ApiException::fromHttpResponse(self::httpResponse(500, [
            'validationResults' => ['errorMessages' => [['code' => 'E1', 'category' => 'KSA', 'message' => 'First']]],
        ]));

        self::assertSame('ZATCA API error (HTTP 500): First [E1]', $exception->getMessage());
        self::assertSame('KSA', $exception->getErrors()[0]->getCategory());
    }

    public function testEmptyBodyFallsBackToTheReasonPhrase(): void
    {
        $exception = ApiException::fromHttpResponse(self::httpResponse(503, null));

        self::assertSame('ZATCA API error (HTTP 503): Service Unavailable', $exception->getMessage());
        self::assertSame([], $exception->getErrors());
        self::assertSame([], $exception->getResponseData());
        self::assertNotNull($exception->getResponse());
        self::assertNotNull($exception->getRequest());
    }

    /**
     * @param array<array-key, mixed>|null $data
     */
    private static function httpResponse(int $status, ?array $data): HttpResponse
    {
        return new HttpResponse(new Response($status), new Request('POST', 'https://example.com'), $data);
    }
}
