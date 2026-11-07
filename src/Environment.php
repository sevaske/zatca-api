<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi;

use Sevaske\ZatcaApi\Exception\InvalidArgumentException;

/**
 * ZATCA e-invoicing environments and their base URIs.
 */
final class Environment
{
    /**
     * Developer portal (Integration Sandbox): mock responses, public test OTPs and credentials.
     */
    public const SANDBOX = 'sandbox';

    /**
     * Simulation: real validation with OTPs from the Fatoora simulation portal.
     */
    public const SIMULATION = 'simulation';

    /**
     * Production ("core"): real invoices.
     */
    public const PRODUCTION = 'production';

    private const BASE_URIS = [
        self::SANDBOX => 'https://gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal',
        self::SIMULATION => 'https://gw-fatoora.zatca.gov.sa/e-invoicing/simulation',
        self::PRODUCTION => 'https://gw-fatoora.zatca.gov.sa/e-invoicing/core',
    ];

    private function __construct() {}

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_keys(self::BASE_URIS);
    }

    public static function isValid(string $environment): bool
    {
        return isset(self::BASE_URIS[$environment]);
    }

    /**
     * @throws InvalidArgumentException for an unknown environment
     */
    public static function baseUri(string $environment): string
    {
        if (!self::isValid($environment)) {
            throw new InvalidArgumentException(sprintf(
                'Unknown ZATCA environment "%s". Expected one of: %s.',
                $environment,
                implode(', ', self::all()),
            ));
        }

        return self::BASE_URIS[$environment];
    }
}
