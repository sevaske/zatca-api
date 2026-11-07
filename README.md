# ZATCA E-Invoicing API PHP Client

[![CI](https://github.com/sevaske/zatca-api/actions/workflows/ci.yml/badge.svg)](https://github.com/sevaske/zatca-api/actions/workflows/ci.yml)
[![Latest Version](https://img.shields.io/packagist/v/sevaske/zatca-api.svg)](https://packagist.org/packages/sevaske/zatca-api)
[![PHP Version](https://img.shields.io/packagist/php-v/sevaske/zatca-api.svg)](https://packagist.org/packages/sevaske/zatca-api)
[![License](https://img.shields.io/packagist/l/sevaske/zatca-api.svg)](LICENSE.md)

A framework-agnostic PHP client for the e-invoicing API of [ZATCA](https://zatca.gov.sa) (Fatoora), Saudi Arabia.
Covers every endpoint of the API: onboarding (compliance and production CSIDs, renewal), compliance checks,
reporting and clearance.

- Onboarding of an EGS unit: compliance CSID, compliance checks, production CSID, renewal
- Reporting (B2C) and clearance (B2B) of invoices, credit and debit notes
- Validation outcomes are **results, not exceptions**: rejected, already reported, cleared with warnings, ...
- Typed credentials: a compliance CSID cannot be used where a production CSID is required
- `SignedInvoice::fromXml()` reads the UUID and computes the invoice hash exactly like ZATCA
- Works with **any PSR-18 HTTP client** (Guzzle, Symfony HttpClient, ...), auto-discovered
- PHP 7.4 – 8.5, no framework dependencies, PHPStan level max

> This is an unofficial, community-maintained library and is not affiliated with ZATCA.
> To generate and sign the invoice XML, see [sevaske/php-zatca-xml](https://github.com/sevaske/php-zatca-xml).

## Installation

```bash
composer require sevaske/zatca-api
```

The library talks HTTP through [PSR-18](https://www.php-fig.org/psr/psr-18/). If your project has no PSR-18 client yet, install one, for example:

```bash
composer require guzzlehttp/guzzle
```

## Quick start

```php
use Sevaske\ZatcaApi\Client;
use Sevaske\ZatcaApi\Credentials\ProductionCsid;
use Sevaske\ZatcaApi\Invoice\SignedInvoice;

$zatca = Client::production();
$csid = ProductionCsid::fromArray($savedCredentials); // see "Onboarding"

$result = $zatca->invoices($csid)->report(SignedInvoice::fromXml($signedXml));

if ($result->isReported()) {
    // accepted (possibly with warnings: $result->hasWarnings())
} else {
    foreach ($result->getValidationResults()->getErrorMessages() as $error) {
        echo $error->getCode(), ': ', $error->getMessage(), PHP_EOL; // "BR-KSA-37: The seller address ..."
    }
}
```

## Environments

| Environment | Constructor | Base URI | Use |
|---|---|---|---|
| Sandbox | `Client::sandbox()` | `.../e-invoicing/developer-portal` | Integration Sandbox: mock responses, public test OTPs |
| Simulation | `Client::simulation()` | `.../e-invoicing/simulation` | real validation, OTPs from the Fatoora simulation portal |
| Production | `Client::production()` | `.../e-invoicing/core` | real invoices |

`new Client(Environment::SIMULATION, $options)` is equivalent to `Client::simulation($options)`.

In the sandbox, OTP `123345` issues a compliance CSID and OTP `123456` renews a production CSID
(`111111` is an invalid OTP, `222222` an expired one).

## Onboarding

Every EGS unit (device or solution instance that issues invoices) is onboarded once:

```php
use Sevaske\ZatcaApi\Client;
use Sevaske\ZatcaApi\Invoice\SignedInvoice;

$zatca = Client::simulation();

// 1. Compliance CSID for your CSR. The OTP is generated in the Fatoora portal.
$complianceCsid = $zatca->onboarding()
    ->requestComplianceCsid($csrPem, $otp)
    ->toComplianceCsid();

// 2. Compliance checks: one signed sample of every document type the unit will issue
//    (standard / simplified invoice, credit note, debit note), signed with $complianceCsid->getCertificate().
foreach ($sampleXmls as $xml) {
    $check = $zatca->compliance($complianceCsid)->checkInvoice(SignedInvoice::fromXml($xml));

    if (!$check->isPassed()) {
        // see $check->getValidationResults()
    }
}

// 3. Production CSID
$productionCsid = $zatca->onboarding()
    ->requestProductionCsid($complianceCsid)
    ->toProductionCsid();

// Save the credentials and the private key of the CSR.
file_put_contents('production-csid.json', json_encode($productionCsid->toArray()));
```

The CSR may be passed as PEM text or as the base64 of it. Credentials are restored with
`ComplianceCsid::fromArray()` / `ProductionCsid::fromArray()`. `getCertificate()` returns the certificate
(base64 DER) used to sign invoices, `getCertificatePem()` the same in PEM format.

### Renewal

A production CSID is renewed with a new CSR and a renewal OTP, authorized by the current CSID:

```php
$renewal = $zatca->onboarding()->renewProductionCsid($productionCsid, $newCsrPem, $otp);

if ($renewal->isIssued()) {
    $productionCsid = $renewal->toProductionCsid();
} elseif ($renewal->isNotCompliant()) {
    // HTTP 428: ZATCA issued a compliance CSID instead. Pass the compliance checks again
    // with $renewal->toComplianceCsid(), then request a production CSID.
}
```

## Signed invoices

`SignedInvoice` is what the compliance, reporting and clearance endpoints accept:

```php
use Sevaske\ZatcaApi\Invoice\SignedInvoice;

// UUID read from cbc:UUID, hash computed from the XML the same way ZATCA does
$invoice = SignedInvoice::fromXml($signedXml);

// or given explicitly, e.g. from your signer
$invoice = new SignedInvoice($signedXml, $invoiceHash, $uuid);
```

With [sevaske/php-zatca-xml](https://github.com/sevaske/php-zatca-xml):

```php
$signed = Saleh7\Zatca\InvoiceSigner::signInvoice($xml, $certificate);
$invoice = new SignedInvoice($signed->getXML(), $signed->getHash(), $uuid);
```

## Reporting and clearance

```php
$invoices = $zatca->invoices($productionCsid);

// Simplified (B2C) documents: report within 24 hours of issuing
$reporting = $invoices->report($invoice);

// Standard (B2B) documents: clear before handing over to the buyer
$clearance = $invoices->clear($invoice);

if ($clearance->isCleared()) {
    $stampedXml = $clearance->getClearedInvoice(); // hand this document over to the buyer
}
```

Every validation outcome is returned as a result:

| HTTP | Endpoint | Meaning | Result |
|---|---|---|---|
| 200 | both | accepted | `isReported()` / `isCleared()` |
| 202 | both | accepted with warnings | `hasWarnings()` |
| 208 | clearance | cleared before, cleared invoice returned again | `isAlreadyCleared()` |
| 303 | clearance | clearance is deactivated, use reporting | `isClearanceDisabled()`, `getMessage()` |
| 303 | reporting | standard invoice sent to reporting while clearance is active | `isClearanceRequired()` |
| 400 | both | rejected, see validation results | `isReported()` / `isCleared()` is false |
| 409 | reporting | already reported | `isAlreadyReported()` |

Reporting requests carry the required `Clearance-Status` header: `1` by default. While clearance is
deactivated for the taxpayer, standard invoices are reported too; configure it with
`ClientOptions::withClearanceEnabled(false)` (`Clearance-Status: 0`).

### Validation results

```php
$validation = $result->getValidationResults();

$validation->getStatus();          // "PASS", "WARNING" or "ERROR"
$validation->isPassed();
$validation->getErrorMessages();   // list of ValidationMessage
$validation->getWarningMessages();
$validation->getInfoMessages();
$validation->getErrorCodes();      // ["BR-KSA-37", "invalid-invoice-hash"]

$message = $validation->getErrorMessages()[0];
$message->getCode();      // "BR-KSA-37"
$message->getCategory();  // "KSA", "EN_16931", "XSD validation", "QRCODE_VALIDATION", ...
$message->getMessage();   // in English, or Arabic with ClientOptions::withLanguage('ar')
```

`getData()` on every result returns the decoded response body.

## Error handling

Every exception thrown by the library implements `Sevaske\ZatcaApi\Exception\ZatcaException`.

```
ZatcaException (interface)
├── InvalidArgumentException      invalid input (CSR, OTP, invoice, credentials), thrown before any request
├── ConfigurationException        no PSR-18 client / PSR-17 factory found
├── TransportException            network error; it is unknown whether ZATCA received the request
└── ApiException                  ZATCA returned an error
    ├── BadRequestException         400 on CSID requests, e.g. Invalid-OTP, Invalid-CSR, Missing-ComplianceSteps
    ├── AuthenticationException     401, wrong CSID or a compliance CSID used in production
    ├── ForbiddenException          403
    ├── NotAcceptableException      406, unsupported Accept-Version
    ├── RateLimitException          429
    ├── ServerException             5xx
    └── UnexpectedResponseException the response could not be understood
```

```php
use Sevaske\ZatcaApi\Exception\ApiException;
use Sevaske\ZatcaApi\Exception\AuthenticationException;
use Sevaske\ZatcaApi\Exception\TransportException;

try {
    $result = $zatca->invoices($csid)->report($invoice);
} catch (AuthenticationException $e) {
    // check the CSID
} catch (ApiException $e) {
    $e->getStatusCode();   // e.g. 500
    $e->getErrors();       // list of ApiError (code, message, category)
    $e->getResponseData(); // decoded error body
    $e->getResponse();     // PSR-7 response
    $e->getRequest();      // PSR-7 request, Authorization and OTP headers redacted
} catch (TransportException $e) {
    // timeout / DNS / connection error: resubmitting the same invoice is safe,
    // ZATCA identifies invoices by their hash
}
```

## Configuration

All options are optional. Anything you don't set is discovered automatically.

```php
use Sevaske\ZatcaApi\Client;
use Sevaske\ZatcaApi\ClientOptions;

$options = ClientOptions::create()
    ->withHttpClient($psr18Client)       // e.g. Guzzle with your timeouts / proxy / retry middleware
    ->withRequestFactory($psr17Factory)
    ->withStreamFactory($psr17Factory)
    ->withLanguage('ar')                 // language of validation messages: "en" (default) or "ar"
    ->withClearanceEnabled(true)         // Clearance-Status of reporting requests (default true)
    ->withBaseUri('https://proxy.example.com/zatca') // overrides the environment's base URI
    ->withUserAgent('MyErp/1.0');

$zatca = Client::production($options);
```

Example with Guzzle timeouts:

```php
$zatca = Client::production(ClientOptions::create()->withHttpClient(
    new GuzzleHttp\Client(['timeout' => 30, 'connect_timeout' => 10])
));
```

## Testing your application

Type-hint `Sevaske\ZatcaApi\Contracts\InvoiceSubmitter` in your services. `Client::invoices()` implements it,
so you can replace it with a fake in tests:

```php
use Sevaske\ZatcaApi\Contracts\InvoiceSubmitter;
use Sevaske\ZatcaApi\Invoice\SignedInvoice;
use Sevaske\ZatcaApi\Response\ClearanceResult;
use Sevaske\ZatcaApi\Response\ReportingResult;

final class FakeInvoiceSubmitter implements InvoiceSubmitter
{
    /** @var list<SignedInvoice> */
    public array $submitted = [];

    public function report(SignedInvoice $invoice): ReportingResult
    {
        $this->submitted[] = $invoice;

        return ReportingResult::fromArray(['reportingStatus' => 'REPORTED']);
    }

    public function clear(SignedInvoice $invoice): ClearanceResult
    {
        $this->submitted[] = $invoice;

        return ClearanceResult::fromArray(['clearanceStatus' => 'CLEARED', 'clearedInvoice' => base64_encode($invoice->getXml())]);
    }
}
```

You can also inject a mock PSR-18 client, such as [`php-http/mock-client`](https://github.com/php-http/mock-client),
via `ClientOptions::withHttpClient()`.

## Upgrading from 2.x

See [UPGRADE.md](UPGRADE.md).

## Development

```bash
composer install
composer test           # PHPUnit
composer analyse        # PHPStan (level max)
```

Code style is enforced with [Laravel Pint](https://laravel.com/docs/pint) (PER Coding Style, see `pint.json`).
Pint requires PHP 8.3+, so it lives in its own `tools/pint` directory and does not affect the library's
PHP 7.4+ dependencies:

```bash
composer pint:install   # once
composer format         # fix code style
composer format:check   # check only (runs in CI)
```

## Contributing

Pull requests are welcome. Please add tests for your changes and make sure `composer check` and `composer format:check` pass.

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
