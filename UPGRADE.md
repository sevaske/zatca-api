# Upgrading to 3.0

3.0 is a rewrite. The ZATCA API is the same, the PHP API is not.

- [From 1.x](#from-1x)
- [From 2.x](#from-2x)

What changes in both cases:

- Credentials are no longer state of the client: they are passed to the API group that needs them
  (`compliance(ComplianceCsid)`, `invoices(ProductionCsid)`).
- A rejected invoice (HTTP 400), an already reported invoice (409), "use the other endpoint" (303) are
  results, not exceptions. Exceptions are thrown for errors only.
- Invoices are passed as a `SignedInvoice` instead of three strings.
- PHP 7.4+ and any PSR-18 client; the PSR-17 factories are discovered automatically.

## From 1.x

### Client

1.x called `request()` on the HTTP client, so it effectively required Guzzle. 3.0 works with any PSR-18 client.

```php
// 1.x
$api = new Api('simulation', new GuzzleHttp\Client(), $certificate, $secret);

// 3.0
$zatca = Client::simulation(); // or Client::simulation(ClientOptions::create()->withHttpClient($guzzle))
```

| 1.x | 3.0 |
|---|---|
| `new Api('sandbox' \| 'simulation' \| 'production', $httpClient)` | `Client::sandbox()`, `Client::simulation()`, `Client::production()` |
| `ZatcaEnvironmentEnum::Simulation` | `Environment::SIMULATION` |
| `$api->setCredentials($certificate, $secret)` | `new ProductionCsid(base64_encode($certificate), $secret)` passed to `invoices()` |
| `AuthToken`, `Api::authToken()` | `$csid->getAuthorizationHeader()` |

### Credentials

1.x stored the decoded certificate (`CertificateResponse::certificate()`). 3.0 credentials hold the
`binarySecurityToken` exactly as returned by ZATCA, which is `base64_encode($certificate)`:

```php
$complianceCsid = new ComplianceCsid(base64_encode($savedCertificate), $savedSecret, $savedRequestId);
$productionCsid = new ProductionCsid(base64_encode($savedCertificate), $savedSecret);
```

### Onboarding

```php
// 1.x
$response = $api->complianceCertificate($csr, $otp);
$api->setCredentials($response->certificate(), $response->secret());
$api->compliance($signedXml, $hash, $uuid);
$production = $api->productionCertificate((string) $response->requestId());

// 3.0
$complianceCsid = $zatca->onboarding()->requestComplianceCsid($csr, $otp)->toComplianceCsid();
$zatca->compliance($complianceCsid)->checkInvoice(new SignedInvoice($signedXml, $hash, $uuid));
$productionCsid = $zatca->onboarding()->requestProductionCsid($complianceCsid)->toProductionCsid();
```

`renewProductionCertificate($csr, $otp)` of 1.x sent the renewal without credentials and with POST.
3.0 sends it as ZATCA specifies (PATCH, authorized by the current production CSID):

```php
$renewal = $zatca->onboarding()->renewProductionCsid($productionCsid, $newCsr, $otp);
```

### Invoices

```php
// 1.x
$api->reporting($signedXml, $hash, $uuid);
$api->reporting($signedXml, $hash, $uuid, false); // Clearance-Status: 0
$api->clearance($signedXml, $hash, $uuid);

// 3.0
$invoice = new SignedInvoice($signedXml, $hash, $uuid); // or SignedInvoice::fromXml($signedXml)
$zatca->invoices($productionCsid)->report($invoice);
Client::production(ClientOptions::create()->withClearanceEnabled(false))->invoices($productionCsid)->report($invoice);
$zatca->invoices($productionCsid)->clear($invoice);
```

1.x replaced a missing hash with `base64_encode('0')`, which ZATCA rejects. 3.0 requires the real hash;
`SignedInvoice::fromXml()` computes it from the signed XML.

### Responses

| 1.x | 3.0 |
|---|---|
| `success()` | `isReported()`, `isCleared()`, `isPassed()`, `isIssued()` |
| `validation()`, `validationStatus()` | `getValidationResults()`, `getValidationResults()->getStatus()` |
| `errors()`, `warnings()`, `info()` (arrays) | `getValidationResults()->getErrorMessages()` etc. (`ValidationMessage` objects) |
| `hasErrors()`, `hasWarnings()` | `getValidationResults()->hasErrors()`, `hasWarnings()` |
| `requestId()`, `secret()`, `binarySecurityToken()` | `getRequestId()`, `getSecret()`, `getBinarySecurityToken()` on `CsidResponse` |
| `certificate()` | `toComplianceCsid()->getCertificate()` / `toProductionCsid()->getCertificate()` |
| `unauthorized()` | `AuthenticationException` |
| `raw()` | `getData()` (decoded body); the PSR-7 response is available on exceptions |

### Errors

1.x threw `ZatcaRequestException('Request failed.', ['errors' => ...])` whenever a response contained
errors, including rejected invoices. In 3.0 a rejected invoice is a result:

```php
// 1.x
try {
    $api->reporting($signedXml, $hash, $uuid);
} catch (ZatcaRequestException $e) {
    $errors = $e->context()['errors'] ?? [];
}

// 3.0
$result = $zatca->invoices($productionCsid)->report($invoice);

if (!$result->isReported()) {
    $errors = $result->getValidationResults()->getErrorMessages();
}
```

| 1.x | 3.0 |
|---|---|
| `ZatcaRequestException` for rejected invoices | a result with `isReported()` / `isCleared()` false |
| `ZatcaRequestException` for HTTP and network errors | `ApiException` subclasses, `TransportException` |
| `ZatcaResponseException` | `UnexpectedResponseException` |
| `Sevaske\ZatcaApi\Interfaces\ZatcaExceptionInterface` | `Sevaske\ZatcaApi\Exception\ZatcaException` |

## From 2.x

### Client

```php
// 2.x
$client = new ZatcaClient($httpClient, $factory, $factory, 'simulation');
$client->setAuthToken(new ZatcaAuth($certificate, $secret));

// 3.0
$zatca = Client::simulation(ClientOptions::create()->withHttpClient($httpClient)); // factories are discovered
```

Credentials are no longer state of the client: they are passed to the API group that needs them.

### Onboarding

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

### Invoices

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

### Errors

A rejected invoice (HTTP 400), an already reported invoice (409), "use reporting" (303) are no longer
exceptions: they are results. Exceptions are thrown for errors only.

| 2.x | 3.0 |
|---|---|
| `ZatcaRequestException` for HTTP errors | `ApiException` and its subclasses (`BadRequestException`, `AuthenticationException`, `ServerException`, ...) |
| `ZatcaRequestException` for network errors | `TransportException` |
| `ZatcaResponseException` | `UnexpectedResponseException` |
| `$e->context()` | `$e->getErrors()`, `$e->getResponseData()`, `$e->getResponse()`, `$e->getRequest()` |
| catch `ZatcaException` | catch the `Sevaske\ZatcaApi\Exception\ZatcaException` interface |

### Middleware

The middleware pipeline is removed. Decorate the PSR-18 client instead (Guzzle middleware,
`php-http/client-common` plugins, ...) and pass it with `ClientOptions::withHttpClient()`.
