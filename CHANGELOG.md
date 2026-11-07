# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [3.0.0] - Unreleased

A rewrite of the client. See [UPGRADE.md](UPGRADE.md) for migrating from 2.x.

### Added

- `Client` with `sandbox()`, `simulation()` and `production()` constructors, PSR-18 / PSR-17 auto-discovery
  and immutable `ClientOptions` (HTTP client, language, clearance status, base URI, user agent).
- API groups per onboarding stage: `onboarding()`, `compliance(ComplianceCsid)`, `invoices(ProductionCsid)`.
- Compliance CSID via `POST /compliance`, production CSID via `POST /production/csids`,
  renewal via `PATCH /production/csids` including HTTP 428 (`NOT_COMPLIANT`).
- Compliance checks via `POST /compliance/invoices`.
- Reporting via `POST /invoices/reporting/single` and clearance via `POST /invoices/clearance/single`
  with the required `Clearance-Status` header.
- Typed credentials `ComplianceCsid` and `ProductionCsid` with `toArray()` / `fromArray()`, certificate in
  base64 DER and PEM, secrets hidden from debug output.
- `SignedInvoice` with `fromXml()`: reads the UUID and computes the ZATCA invoice hash.
- Results for every validation outcome: `ComplianceCheckResult`, `ReportingResult`, `ClearanceResult`
  (HTTP 200, 202, 208, 303, 400, 409) with `ValidationResults` and `ValidationMessage`.
- Exception hierarchy mapped from HTTP status codes, all implementing `ZatcaException`; requests attached to
  exceptions have the Authorization and OTP headers redacted.
- `InvoiceSubmitter` contract for faking the API in tests.

### Changed

- PHP 7.4+ with `php-http/discovery`; `sevaske/support` is no longer required.

### Removed

- `ZatcaClient`, `ZatcaAuth`, `ZatcaEnvironment`, middleware, request classes and the 2.x responses.
