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

    public function testPlainTextBodyIsQuotedInTheMessage(): void
    {
        // the developer-portal sandbox answers a rejected CSR with "Invalid Request" and Content-Type: application/json
        $exception = self::fromBody(400, "Invalid Request\n");

        self::assertSame('ZATCA API error (HTTP 400): Invalid Request', $exception->getMessage());
        self::assertSame([], $exception->getErrors(), 'only errors reported by ZATCA in a known format');
        self::assertSame([], $exception->getResponseData());
        self::assertSame("Invalid Request\n", $exception->getResponseBody());
    }

    public function testLongBodyIsTruncatedInTheMessageOnly(): void
    {
        $body = "<html>\n  <head><title>502 Bad Gateway</title></head>\n  <body>" . str_repeat('x', 200) . '</body></html>';
        $exception = self::fromBody(502, $body);

        self::assertSame(
            'ZATCA API error (HTTP 502): <html> <head><title>502 Bad Gateway</title></head> <body>'
            . str_repeat('x', 63) . ' (truncated...)',
            $exception->getMessage(),
        );
        self::assertSame($body, $exception->getResponseBody());
    }

    public function testTruncationKeepsMultibyteCharactersIntact(): void
    {
        $exception = self::fromBody(400, str_repeat('ж', 121));

        self::assertSame('ZATCA API error (HTTP 400): ' . str_repeat('ж', 120) . ' (truncated...)', $exception->getMessage());
    }

    public function testBodyOfExactlyTheSummaryLengthIsNotTruncated(): void
    {
        $exception = self::fromBody(400, str_repeat('a', 120));

        self::assertSame('ZATCA API error (HTTP 400): ' . str_repeat('a', 120), $exception->getMessage());
    }

    public function testBinaryBodyFallsBackToTheReasonPhrase(): void
    {
        $body = "\x1f\x8b\x08\x00binary";
        $exception = self::fromBody(500, $body);

        self::assertSame('ZATCA API error (HTTP 500): Internal Server Error', $exception->getMessage());
        self::assertSame($body, $exception->getResponseBody());
    }

    public function testJsonBodyWithoutKnownErrorFieldsIsQuoted(): void
    {
        $response = new Response(400, [], '{"foo":"bar"}');
        $exception = ApiException::fromHttpResponse(new HttpResponse($response, new Request('POST', 'https://example.com'), ['foo' => 'bar']));

        self::assertSame('ZATCA API error (HTTP 400): {"foo":"bar"}', $exception->getMessage());
        self::assertSame([], $exception->getErrors());
        self::assertSame(['foo' => 'bar'], $exception->getResponseData());
    }

    public function testResponseBodyIsReadableAfterTheStreamWasConsumed(): void
    {
        $exception = self::fromBody(406, 'This Version is not supported or not provided in the header.');

        $psrResponse = $exception->getResponse();
        self::assertNotNull($psrResponse);
        $psrResponse->getBody()->getContents(); // the caller reads the stream to its end

        self::assertSame('This Version is not supported or not provided in the header.', $exception->getResponseBody());
    }

    private static function fromBody(int $status, string $body): ApiException
    {
        return ApiException::fromHttpResponse(new HttpResponse(new Response($status, [], $body), new Request('POST', 'https://example.com'), null));
    }

    /**
     * @param array<array-key, mixed>|null $data
     */
    private static function httpResponse(int $status, ?array $data): HttpResponse
    {
        return new HttpResponse(new Response($status), new Request('POST', 'https://example.com'), $data);
    }
}
