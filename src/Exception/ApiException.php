<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Exception;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Sevaske\ZatcaApi\Http\HttpResponse;
use Sevaske\ZatcaApi\Response\ApiError;

/**
 * Thrown when the ZATCA API responds with an error.
 *
 * Validation results of invoices (rejected, already reported, ...) are not errors:
 * they are returned as result objects. Use {@see ApiException::fromHttpResponse()} to get
 * the most specific subclass for a given status code.
 */
class ApiException extends \RuntimeException implements ZatcaException
{
    /**
     * Specific exception classes for known status codes. All 5xx codes map to {@see ServerException}.
     */
    private const EXCEPTION_CLASS_BY_STATUS_CODE = [
        400 => BadRequestException::class,
        401 => AuthenticationException::class,
        403 => ForbiddenException::class,
        406 => NotAcceptableException::class,
        429 => RateLimitException::class,
    ];

    private int $statusCode;

    /**
     * @var array<array-key, mixed>
     */
    private array $responseData;

    /**
     * @var list<ApiError>
     */
    private array $errors;

    private ?ResponseInterface $response;

    private ?RequestInterface $request;

    /**
     * @param array<array-key, mixed> $responseData
     * @param list<ApiError>          $errors
     */
    public function __construct(
        string $message,
        int $statusCode,
        array $responseData = [],
        array $errors = [],
        ?ResponseInterface $response = null,
        ?RequestInterface $request = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $statusCode, $previous);

        $this->statusCode = $statusCode;
        $this->responseData = $responseData;
        $this->errors = $errors;
        $this->response = $response;
        $this->request = $request;
    }

    /**
     * Creates the most specific exception for the given error response.
     *
     * @internal
     */
    public static function fromHttpResponse(HttpResponse $httpResponse): self
    {
        $statusCode = $httpResponse->getStatusCode();
        $data = $httpResponse->getData();
        $errors = self::extractErrors($data);
        $exceptionClass = self::exceptionClassFor($statusCode);

        return new $exceptionClass(
            self::resolveMessage($httpResponse->getResponse(), $errors, $statusCode),
            $statusCode,
            $data,
            $errors,
            $httpResponse->getResponse(),
            $httpResponse->getRequest(),
        );
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Errors reported by ZATCA, e.g. [Invalid-OTP] or [Invalid-ComplianceRequestId].
     *
     * @return list<ApiError>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Decoded JSON body of the error response (empty when the body was not JSON).
     *
     * @return array<array-key, mixed>
     */
    public function getResponseData(): array
    {
        return $this->responseData;
    }

    public function getResponse(): ?ResponseInterface
    {
        return $this->response;
    }

    /**
     * The failed request; the Authorization and OTP headers are redacted.
     */
    public function getRequest(): ?RequestInterface
    {
        return $this->request;
    }

    /**
     * Supports the error formats of ZATCA:
     * - {"errors": [{"code": "...", "message": "..."}]}            certificate endpoints
     * - {"validationResults": {"errorMessages": [...]}}             invoice endpoints
     * - {"code": "...", "message": "..."} / {"category": ...}       internal errors
     * - {"timestamp": ..., "status": 401, "error": "Unauthorized"}  gateway errors
     *
     * @param array<array-key, mixed> $data
     *
     * @return list<ApiError>
     */
    private static function extractErrors(array $data): array
    {
        $list = $data['errors'] ?? null;

        if (!is_array($list)) {
            $validationResults = $data['validationResults'] ?? null;
            $list = is_array($validationResults) ? ($validationResults['errorMessages'] ?? null) : null;
        }

        if (is_array($list)) {
            $errors = [];

            foreach ($list as $item) {
                if (is_array($item)) {
                    $errors[] = ApiError::fromArray($item);
                }
            }

            return $errors;
        }

        $message = $data['message'] ?? null;
        $error = $data['error'] ?? null;

        if ((is_string($message) && trim($message) !== '') || isset($data['code'])) {
            return [ApiError::fromArray($data)];
        }

        if (is_string($error) && trim($error) !== '') {
            return [new ApiError(null, trim($error))];
        }

        return [];
    }

    /**
     * @return class-string<ApiException>
     */
    private static function exceptionClassFor(int $statusCode): string
    {
        if (isset(self::EXCEPTION_CLASS_BY_STATUS_CODE[$statusCode])) {
            return self::EXCEPTION_CLASS_BY_STATUS_CODE[$statusCode];
        }

        if ($statusCode >= 500) {
            return ServerException::class;
        }

        return self::class;
    }

    /**
     * "ZATCA API error (HTTP 400): The provided OTP is invalid [Invalid-OTP]"
     *
     * @param list<ApiError> $errors
     */
    private static function resolveMessage(ResponseInterface $response, array $errors, int $statusCode): string
    {
        $message = sprintf('ZATCA API error (HTTP %d)', $statusCode);

        foreach ($errors as $error) {
            if ($error->getMessage() !== '') {
                return $message . ': ' . $error->getMessage() . ($error->getCode() !== null ? ' [' . $error->getCode() . ']' : '');
            }
        }

        $reasonPhrase = $response->getReasonPhrase();

        return $reasonPhrase !== '' ? $message . ': ' . $reasonPhrase : $message;
    }
}
