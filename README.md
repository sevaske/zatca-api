<p align="center">
<img src="https://badgen.net/packagist/php/sevaske/zatca-api" alt="php Vers ion">
<a href="https://packagist.org/packages/sevaske/zatca-api"><img alt="Packagist Stars" src="https://img.shields.io/packagist/stars/sevaske/zatca-api"></a>
<a href="https://packagist.org/packages/sevaske/zatca-api"><img alt="Packagist Downloads" src="https://img.shields.io/packagist/dt/sevaske/zatca-api"></a>
<a href="https://packagist.org/packages/sevaske/zatca-api"><img alt="Packagist Version" src="https://img.shields.io/packagist/v/sevaske/zatca-api"></a>
<a href="https://packagist.org/packages/sevaske/zatca-api"><img alt="License" src="https://img.shields.io/badge/License-MIT-yellow.svg"></a>
</p>

# ZATCA API PHP Client

This is a simple PHP library to work with the ZATCA API. You can send invoice data, check invoice status, verify compliance, and manage certificates easily.

If you’re looking for a library to generate XML invoices, you can use this one: https://github.com/sevaske/php-zatca-xml

---

## Features

- Supports all major ZATCA API endpoints
- Simple authentication with certificate and secret
- Response handling via typed response objects
- Environment support: sandbox, simulation, production
- Uses any PSR-18 compatible HTTP client (e.g., Guzzle). Clients other than Guzzle need `guzzlehttp/psr7` or `nyholm/psr7` installed to build requests


## Installation
```bash
composer require sevaske/zatca-api
```


## Quick Start

```php
use GuzzleHttp\Client;
use Sevaske\ZatcaApi\Api;
use Sevaske\ZatcaApi\Responses\ClearanceResponse;
use Sevaske\ZatcaApi\Responses\ComplianceCertificateResponse;
use Sevaske\ZatcaApi\Responses\ProductionCertificateResponse;
use Sevaske\ZatcaApi\Responses\ReportingResponse;
use Sevaske\ZatcaApi\Exceptions\ZatcaRequestException;

$httpClient = new Client();

$api = new Api('simulation', $httpClient);

try {
    $response = $api->complianceCertificate('my csr', 'otp code'); // ComplianceCertificateResponse
    
    if (! $response->success()) {
        // handle
    }
    
    $credentials = [
        'requestId' => $response->requestId(),
        'secret' => $response->secret(),
        'certificate' => $response->certificate(),
    ];
    
    // saving the certificate
    file_put_contents('credentials.json', json_encode($credentials));
    
    // set the credentials to auth
    $api->setCredentials($credentials['certificate'], $credentials['secret']);
    
    $productionCertificate = $api->productionCertificate($credentials['requestId']); // ProductionCertificateResponse
    
    if (! $productionCertificate->success()) {
        // handle
    }
    
    $productionCredentials = [
        'requestId' => $productionCertificate->requestId(),
        'secret' => $productionCertificate->secret(),
        'certificate' => $productionCertificate->certificate(),
    ];
    
    // saving the certificate
    file_put_contents('production-credentials.json', json_encode($productionCredentials));
    
    // set the credentials to auth
    $api->setCredentials($productionCredentials['certificate'], $productionCredentials['secret']);
    
    $reportingResponse = $api->reporting('signed-xml-invoice', 'invoice-hash', 'uuid', true); // ReportingResponse
    $reportingResponse->success();
    $reportingResponse->warnings();
    
    $clearanceResponse = $api->clearance('signed-xml-invoice', 'invoice-hash', 'uuid', true); // ClearanceResponse
    $reportingResponse->success();
    $reportingResponse->warnings();
} catch (ZatcaRequestException $e) {
    echo "Request error: " . $e->getMessage();
    print_r($e->context());
}
```


## Available Methods

| Method                      | Description                                       |
|-----------------------------|-------------------------------------------------|
| `reporting()`               | Submit invoice data to ZATCA                      |
| `clearance()`               | Request invoice clearance status                   |
| `compliance()`              | Verify invoice compliance                          |
| `complianceCertificate()`  | Request compliance certificate                     |
| `productionCertificate()`  | Obtain production certificate                      |
| `renewProductionCertificate()` | Renew production certificate                    |


## Notes

- `$invoiceHash` may be `null` for `reporting()`, `clearance()` and `compliance()`: the hash is then taken from the
  signature of the signed invoice (the digest of the `invoiceSignedData` reference).
- Every response has `statusCode()` (the HTTP status) and `success()`.
- If your HTTP client does not throw on HTTP errors (e.g. Guzzle with `http_errors => false` or a pure PSR-18 client),
  responses like 401 or 500 are returned as response objects: always check `success()`.


## Exception handling

The library throws the following exceptions which you can catch and handle:

- `ZatcaException` — general exception class
- `ZatcaRequestException` — errors during the HTTP request
- `ZatcaResponseException` — errors processing the API response

When ZATCA answers with an HTTP error (e.g. a rejected invoice), the exception context contains the answer:

```php
try {
    $api->reporting($xml, $hash, $uuid);
} catch (ZatcaRequestException $e) {
    $context = $e->context();

    $context['status'];   // HTTP status, e.g. 400
    $context['errors'];   // ZATCA errors: validationResults.errorMessages for invoices, errors for certificates
    $context['response']; // decoded JSON body
    $context['content'];  // raw body
}
```

`status`, `content`, `response` and `errors` are present only when the HTTP client provides the error response
(Guzzle does); `response` and `errors` only when the body is JSON.


## Renewing the production certificate

Renewal is authorized by the **current** production certificate and requires an OTP from the Fatoora portal and a new CSR.

```php
$api->setCredentials($currentProductionCertificate, $currentSecret);

$renewal = $api->renewProductionCertificate($newCsr, $otp);

if ($renewal->success()) {
    // store $renewal->certificate() and $renewal->secret()
} elseif ($renewal->isNotCompliant()) {
    // HTTP 428: ZATCA issued a compliance certificate. Pass the compliance checks with it,
    // then call productionCertificate($renewal->requestId())
}
```


## Security

- The certificate secret is a credential: store it encrypted or in a secret manager, never in a public directory or in git.
  The `file_put_contents()` calls in the example above only illustrate the flow.
- Do not disable TLS verification in the HTTP client.
- Exceptions keep the original HTTP client exception as `getPrevious()`; it may reference the request including the
  `Authorization` header, so do not dump it to logs.

