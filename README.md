<p align="center">
<img src="https://badgen.net/packagist/php/sevaske/zatca-api" alt="php Vers ion">
<a href="https://packagist.org/packages/sevaske/zatca-api"><img alt="Packagist Stars" src="https://img.shields.io/packagist/stars/sevaske/zatca-api"></a>
<a href="https://packagist.org/packages/sevaske/zatca-api"><img alt="Packagist Downloads" src="https://img.shields.io/packagist/dt/sevaske/zatca-api"></a>
<a href="https://packagist.org/packages/sevaske/zatca-api"><img alt="Packagist Version" src="https://img.shields.io/packagist/v/sevaske/zatca-api"></a>
<a href="https://packagist.org/packages/sevaske/zatca-api"><img alt="License" src="https://img.shields.io/badge/License-MIT-yellow.svg"></a>
</p>

# ZATCA API PHP Client

This is a simple PHP library to work with the ZATCA API. You can send invoice data and manage certificates easily.

⚠️ **Note:** This is an unofficial library and not maintained by ZATCA. I do not provide personal support or consulting.

If you’re looking for a library to generate XML invoices, you can use this one: https://github.com/sevaske/php-zatca-xml

---

## Features

- Full coverage of ZATCA API endpoints (reporting, clearance, compliance)
- Authentication via certificate and secret or auth token
- Supports middleware for request/response processing
- Typed response objects for easy validation and error handling
- Supports multiple environments: sandbox, simulation, production
- Follows PSR standards (PSR-4, PSR-7, PSR-17, PSR-18)
- Works with any PSR-18 compatible HTTP client (e.g., Guzzle)


## Installation
```bash
composer require sevaske/zatca-api:^2.0
```

## Usage

#### Client Initialization

Create HTTP client and factories for PSR-17 / PSR-18. For example, GuzzleHttp

```php
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Sevaske\ZatcaApi\ZatcaClient;

$httpClient = new Client();
$factory = new HttpFactory();

// Initialize ZatcaClient with sandbox environment
$client = new ZatcaClient(
    $httpClient,
    $factory, // RequestFactoryInterface
    $factory, // StreamFactoryInterface
    'sandbox' // environment: sandbox | simulation | production
);
```

#### Compliance Certificate Request

```php
use Sevaske\ZatcaApi\Exceptions\ZatcaRequestException;
use Sevaske\ZatcaApi\Exceptions\ZatcaResponseException;

try {
    /**
    * @var $client \Sevaske\ZatcaApi\ZatcaClient 
    */
    $certificateResponse = $client->complianceCertificate('your .csr file content', '112233');
} catch (ZatcaRequestException|ZatcaResponseException $e) {
    // handle
}
```

#### Authorized requests

Create AuthToken from compliance certificate to make authorized requests.

```php
/**
* @var $certificateResponse \Sevaske\ZatcaApi\Responses\CertificateResponse
* @var $client \Sevaske\ZatcaApi\ZatcaClient
 */
$authToken = new ZatcaAuth($certificateResponse->certificate(), $certificateResponse->secret());
$client->setAuthToken($authToken);
```

#### Submitting Invoices

Once you have a valid compliance certificate and auth token, you can submit invoices in the simulation environment.

**Submitting 6 documents is required to switch to production mode.**


```php
use Sevaske\ZatcaApi\Exceptions\ZatcaRequestException;
use Sevaske\ZatcaApi\Exceptions\ZatcaResponseException;

try {
    // B2P
    $client->reportingInvoice('b2p invoice xml', 'hash', 'uuid');
    $client->reportingInvoice('b2p debit note xml', 'hash', 'uuid');
    $client->reportingInvoice('b2p credit note xml', 'hash', 'uuid');

    // B2B
    $client->clearanceInvoice('b2b invoice xml', 'hash', 'uuid');
    $client->clearanceInvoice('b2b debit note xml', 'hash', 'uuid');
    $client->clearanceInvoice('b2b credit note xml', 'hash', 'uuid');
} catch (ZatcaRequestException|ZatcaResponseException $e) {
    // handle
}
```

#### Production Onboarding

After submitting the required simulation invoices, you can request a production certificate.

**This certificate allows you to submit real invoices in the production environment.**

```php
use Sevaske\ZatcaApi\Exceptions\ZatcaRequestException;
use Sevaske\ZatcaApi\Exceptions\ZatcaResponseException;

/**
* @var $client \Sevaske\ZatcaApi\ZatcaClient
*/

try {
    $productionCertificateResponse = $client->productionCertificate($certificateResponse->requestId());
} catch (ZatcaRequestException|ZatcaResponseException $e) {
    // handle
}
```

#### Submitting Production Invoices

Once the client is configured with the production certificate and environment, you can submit real invoices to ZATCA.

```php
use Sevaske\ZatcaApi\ZatcaAuth;
use Sevaske\ZatcaApi\Exceptions\ZatcaRequestException;
use Sevaske\ZatcaApi\Exceptions\ZatcaResponseException;

/**
* @var $client \Sevaske\ZatcaApi\ZatcaClient
* @var $productionCertificateResponse \Sevaske\ZatcaApi\Responses\ProductionCertificateResponse
*/
$productionClient = $client->withEnvironment('production');
$productionAuth = ZatcaAuth($productionCertificateResponse->certificate(), $productionCertificateResponse->secret());
$client->setAuthToken($productionAuth);

try {
    // submitting production invoices
    $productionClient->reportingInvoice('my real B2P invoice xml', 'hash', 'uuid');
    $productionClient->clearanceInvoice('my real B2P invoice xml', 'hash', 'uuid');
} catch (ZatcaRequestException|ZatcaResponseException $e) {
    // handle
}
```


## Middleware

Middleware in `ZatcaClient` allows you to inspect, modify, or wrap HTTP requests and responses. It works as a pipeline, meaning that multiple middleware can be chained together, each receiving the request and a `$next` callable that continues to the next middleware and ultimately to the HTTP client.

`ZatcaClient` provides three ways to manage middleware:

1. **`withMiddleware($middleware)`** – returns a **new cloned instance** with the provided middleware. Existing middleware in the original client is **replaced** in the clone.
2. **`setMiddleware($middleware)`** – **mutates the current instance**, replacing its middleware with the given ones.
3. **`attachMiddleware($middleware)`** – **mutates the current instance**, adding the given middleware to the end of the existing middleware stack.


All middleware must implement the `MiddlewareInterface`:

```php
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

interface MiddlewareInterface
{
    /**
     * @param RequestInterface $request The incoming request
     * @param callable $next Callable to forward the request to the next middleware or the HTTP client
     * @return ResponseInterface
     */
    public function handle(RequestInterface $request, callable $next): ResponseInterface;
}
```

#### Example

For example, implementation of "logging" requests and responses:

```php

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Sevaske\ZatcaApi\Interfaces\MiddlewareInterface;
use Sevaske\ZatcaApi\Responses\ZatcaResponse;

// Attach a custom middleware to inspect requests and responses
$client = $client->withMiddleware(new class implements MiddlewareInterface
{
    public function handle(RequestInterface $request, callable $next): ResponseInterface
    {
        // Log request info
        $this->info("URL: " . (string) $request->getUri());
        $this->info("Body: " . $this->safeStreamContents($request->getBody()));

        // proceed with request
        $response = $next($request);

        // Log response info
        $this->info("Response:");
        print_r(ZatcaResponse::parse($response));

        return $response;
    }

    private function safeStreamContents(\Psr\Http\Message\StreamInterface $stream): string
    {
        if (! $stream->isSeekable()) {
            return '[unseekable stream]';
        }

        $pos = $stream->tell();
        $stream->rewind();
        $content = $stream->getContents();
        $stream->seek($pos);

        return $content;
    }

    private function info(string $text): void
    {
        echo "\n\r" . $text;
    }
});
```


## Exception handling

The library throws the following exceptions which you can catch and handle:

- `ZatcaException` — general exception class
- `ZatcaRequestException` — errors during the HTTP request
- `ZatcaResponseException` — errors processing the API response

