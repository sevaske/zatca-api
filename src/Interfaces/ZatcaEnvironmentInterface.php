<?php

namespace Sevaske\ZatcaApi\Interfaces;

/**
 * Interface ZatcaEnvironmentInterface
 *
 * Represents a ZATCA API environment, such as sandbox, simulation, or production.
 * Provides methods to retrieve the base URL and construct full URLs for endpoints.
 */
interface ZatcaEnvironmentInterface
{
    /**
     * Get the base URL for this environment.
     *
     * Example:
     * - Sandbox: https://gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal
     * - Simulation: https://gw-fatoora.zatca.gov.sa/e-invoicing/simulation
     * - Production: https://gw-fatoora.zatca.gov.sa/e-invoicing/core
     *
     * @return string The base URL for API requests in this environment.
     */
    public function baseUrl(): string;

    /**
     * Build a full URL for a given endpoint or path within this environment.
     *
     * Automatically ensures exactly one slash between base URL and path.
     * Throws InvalidArgumentException if $uri is empty or invalid.
     *
     * @param string $uri Relative path or endpoint (e.g. '/compliance/invoices')
     * @return string Fully qualified URL (e.g. https://.../compliance/invoices)
     */
    public function url(string $uri): string;
}