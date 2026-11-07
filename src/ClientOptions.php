<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Sevaske\ZatcaApi\Exception\InvalidArgumentException;

/**
 * Immutable optional configuration of {@see Client}.
 *
 *     $options = ClientOptions::create()
 *         ->withHttpClient($guzzle)   // any PSR-18 client
 *         ->withLanguage('ar');       // language of ZATCA validation messages
 *
 * Anything not configured explicitly is discovered automatically (php-http/discovery).
 */
final class ClientOptions
{
    public const LANGUAGE_ENGLISH = 'en';

    public const LANGUAGE_ARABIC = 'ar';

    private ?ClientInterface $httpClient = null;

    private ?RequestFactoryInterface $requestFactory = null;

    private ?StreamFactoryInterface $streamFactory = null;

    private ?string $baseUri = null;

    private string $language = self::LANGUAGE_ENGLISH;

    private bool $clearanceEnabled = true;

    private ?string $userAgent = null;

    public static function create(): self
    {
        return new self();
    }

    /**
     * Uses the given PSR-18 HTTP client (configure timeouts, proxies, retries etc. on it).
     */
    public function withHttpClient(ClientInterface $httpClient): self
    {
        $clone = clone $this;
        $clone->httpClient = $httpClient;

        return $clone;
    }

    public function withRequestFactory(RequestFactoryInterface $requestFactory): self
    {
        $clone = clone $this;
        $clone->requestFactory = $requestFactory;

        return $clone;
    }

    public function withStreamFactory(StreamFactoryInterface $streamFactory): self
    {
        $clone = clone $this;
        $clone->streamFactory = $streamFactory;

        return $clone;
    }

    /**
     * Overrides the base URI of the environment (useful for proxies and testing).
     */
    public function withBaseUri(string $baseUri): self
    {
        $baseUri = rtrim(trim($baseUri), '/');

        if (!self::isHttpUrl($baseUri)) {
            throw new InvalidArgumentException(sprintf('Invalid base URI "%s".', $baseUri));
        }

        $clone = clone $this;
        $clone->baseUri = $baseUri;

        return $clone;
    }

    /**
     * Language of ZATCA validation messages: "en" (default) or "ar".
     */
    public function withLanguage(string $language): self
    {
        $language = strtolower(trim($language));

        if (!in_array($language, [self::LANGUAGE_ENGLISH, self::LANGUAGE_ARABIC], true)) {
            throw new InvalidArgumentException(sprintf('Unsupported language "%s". Expected "en" or "ar".', $language));
        }

        $clone = clone $this;
        $clone->language = $language;

        return $clone;
    }

    /**
     * Whether clearance is enabled for the taxpayer (default true).
     *
     * Sent as the "Clearance-Status" header of reporting requests: "1" when enabled, "0" when disabled.
     * While clearance is disabled, standard invoices are submitted through reporting as well.
     */
    public function withClearanceEnabled(bool $enabled): self
    {
        $clone = clone $this;
        $clone->clearanceEnabled = $enabled;

        return $clone;
    }

    /**
     * Overrides the User-Agent header sent with every request.
     */
    public function withUserAgent(string $userAgent): self
    {
        $clone = clone $this;
        $clone->userAgent = $userAgent;

        return $clone;
    }

    public function getHttpClient(): ?ClientInterface
    {
        return $this->httpClient;
    }

    public function getRequestFactory(): ?RequestFactoryInterface
    {
        return $this->requestFactory;
    }

    public function getStreamFactory(): ?StreamFactoryInterface
    {
        return $this->streamFactory;
    }

    /**
     * Custom base URI, or null to use the environment's default.
     */
    public function getBaseUri(): ?string
    {
        return $this->baseUri;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function isClearanceEnabled(): bool
    {
        return $this->clearanceEnabled;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    private static function isHttpUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true);
    }
}
