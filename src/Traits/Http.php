<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Traits;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Sevaske\ZatcaApi\Exceptions\ZatcaRequestException;
use Throwable;

trait Http
{
    // PSR-18 HTTP client instance
    protected ClientInterface $client;

    // PSR-17 request factory to create HTTP request objects
    protected RequestFactoryInterface $requestFactory;

    // PSR-17 stream factory to create request body streams
    protected StreamFactoryInterface $streamFactory;

    /**
     * Returns the current HTTP client instance.
     */
    public function getClient(): ClientInterface
    {
        return $this->client;
    }

    /**
     * Prepares a PSR-7 HTTP request object with optional query parameters,
     * headers, and body. This method is responsible for creating a valid
     * RequestInterface instance before sending it with the HTTP client.
     *
     * @param  string  $method  The HTTP method (GET, POST, PUT, etc.)
     * @param  string  $uri  The full URI or relative path
     * @param  array  $options  Supported keys:
     *                          - headers: array of HTTP headers
     *                          - query:   array of query parameters
     *                          - body:    string|array request body
     *
     * @throws ZatcaRequestException if request creation fails
     */
    protected function prepareRequest(string $method, string $uri, array $options = []): RequestInterface
    {
        try {
            // create a base request using the PSR-17 request factory
            $request = $this->requestFactory->createRequest($method, $uri);

            // query
            if (array_key_exists('query', $options)) {
                // Convert query array into a URL-encoded query string
                $queryString = http_build_query($options['query']);
                $uriWithQuery = $request->getUri()->withQuery($queryString);
                $request = $request->withUri($uriWithQuery);
            }

            // headers
            if (array_key_exists('headers', $options)) {
                foreach ($options['headers'] as $name => $value) {
                    $request = $request->withHeader($name, $value);
                }
            }

            // body
            if (array_key_exists('body', $options)) {
                $body = $options['body'];

                // convert arrays to JSON or URL-encoded strings depending on Content-Type
                if (is_array($body)) {
                    $contentType = $options['headers']['Content-Type'] ?? 'application/json';

                    if (stripos($contentType, 'json') !== false) {
                        // encode array as JSON if the content type is JSON
                        $body = json_encode($body, JSON_THROW_ON_ERROR);
                    } else {
                        // otherwise, encode as application/x-www-form-urlencoded
                        $body = http_build_query($body);
                    }
                }

                // create a PSR-7 stream for the request body
                $stream = $this->streamFactory->createStream((string) $body);
                $request = $request->withBody($stream);
            }
        } catch (Throwable $e) {
            // wrap any error in a ZatcaRequestException for consistent error handling
            throw new ZatcaRequestException(
                $e->getMessage(),
                ['method' => $method, 'uri' => $uri, 'options' => $options],
                0,
                $e
            );
        }

        return $request;
    }
}
