# Upgrading from 2.x to 3.0

3.0 is a rewrite. The ZATCA API is the same, the PHP API is not.

## Client

```php
// 2.x
$client = new ZatcaClient($httpClient, $factory, $factory, 'simulation');
$client->setAuthToken(new ZatcaAuth($certificate, $secret));

// 3.0
$zatca = Client::simulation(ClientOptions::create()->withHttpClient($httpClient)); // factories are discovered
```

Credentials are no longer state of the client: they are passed to the API group that needs them.

## Onboarding

```php
// 2.x
$response = $client->complianceCertificate($csr, $otp);
$client->setAuthToken(new ZatcaAuth($response->certificate(), $response->secret()));
$production = $client->productionCertificate($response->requestId());

// 3.0
$complianceCsid = $zatca->onboarding()->requestComplianceCsid($csr, $otp)->toComplianceCsid();
$productionCsid = $zatca->onboarding()->requestProductionCsid($complianceCsid)->toProductionCsid();
```

Saved 2.x credentials hold the decoded certificate; 3.0 credentials hold the `binarySecurityToken` exactly
as returned by ZATCA, which is `base64_encode($certificate)`:

```php
$productionCsid = new ProductionCsid(base64_encode($savedCertificate), $savedSecret);
```

## Invoices

```php
// 2.x
$response = $client->reportingInvoice($xml, $hash, $uuid);
$response = $client->clearanceInvoice($xml, $hash, $uuid);
$response = $client->complianceInvoice($xml, $hash, $uuid);

// 3.0
$invoice = new SignedInvoice($xml, $hash, $uuid); // or SignedInvoice::fromXml($xml)
$result = $zatca->invoices($productionCsid)->report($invoice);
$result = $zatca->invoices($productionCsid)->clear($invoice);
$result = $zatca->compliance($complianceCsid)->checkInvoice($invoice);
```

| 2.x | 3.0 |
|---|---|
| `success()` | `isReported()`, `isCleared()`, `isPassed()` |
| `status()` | `getReportingStatus()`, `getClearanceStatus()` |
| `errors()`, `warnings()`, `info()` (arrays) | `getValidationResults()->getErrorMessages()` etc. (`ValidationMessage` objects) |
| `clearedInvoice()` (base64) | `getClearedInvoice()` (XML), `getClearedInvoiceBase64()` |
| `withLanguage('ar')` | `ClientOptions::withLanguage('ar')` |
| `reportingInvoice(..., false)` | `ClientOptions::withClearanceEnabled(false)` |
| `renewProductionCertificate($csr, $otp)` | `onboarding()->renewProductionCsid($productionCsid, $csr, $otp)` |

## Errors

A rejected invoice (HTTP 400), an already reported invoice (409), "use reporting" (303) are no longer
exceptions: they are results. Exceptions are thrown for errors only.

| 2.x | 3.0 |
|---|---|
| `ZatcaRequestException` for HTTP errors | `ApiException` and its subclasses (`BadRequestException`, `AuthenticationException`, `ServerException`, ...) |
| `ZatcaRequestException` for network errors | `TransportException` |
| `ZatcaResponseException` | `UnexpectedResponseException` |
| `$e->context()` | `$e->getErrors()`, `$e->getResponseData()`, `$e->getResponse()`, `$e->getRequest()` |
| catch `ZatcaException` | catch the `Sevaske\ZatcaApi\Exception\ZatcaException` interface |

## Middleware

The middleware pipeline is removed. Decorate the PSR-18 client instead (Guzzle middleware,
`php-http/client-common` plugins, ...) and pass it with `ClientOptions::withHttpClient()`.
