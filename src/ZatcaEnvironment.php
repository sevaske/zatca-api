<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi;

use InvalidArgumentException;
use Sevaske\ZatcaApi\Interfaces\ZatcaEnvironmentInterface;

class ZatcaEnvironment implements ZatcaEnvironmentInterface
{
    public const SANDBOX = 'sandbox';

    public const SIMULATION = 'simulation';

    public const PRODUCTION = 'production';

    /*
     * @var string The current environment value
     */
    protected string $environment;

    /**
     * Constructor
     *
     * @param  string  $environment  One of the defined environment constants
     *
     * @throws InvalidArgumentException if an invalid value is provided
     */
    public function __construct(string $environment)
    {
        // validate that the provided environment is one of the allowed values
        if (! in_array($environment, self::values(), true)) {
            throw new InvalidArgumentException('Invalid environment: '.$environment);
        }

        $this->environment = $environment;
    }

    public function __toString(): string
    {
        return $this->environment;
    }

    /**
     * Get the base URL for the current environment
     *
     * @return string The corresponding ZATCA API URL
     */
    public function baseUrl(): string
    {
        switch ($this->environment) {
            case self::SANDBOX:
                return 'https://gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal';
            case self::SIMULATION:
                return 'https://gw-fatoora.zatca.gov.sa/e-invoicing/simulation';
            case self::PRODUCTION:
                return 'https://gw-fatoora.zatca.gov.sa/e-invoicing/core';
            default:
                throw new InvalidArgumentException('Unknown environment.');
        }
    }

    public function url(string $uri): string
    {
        // remove any leading slashes from the URI to avoid double slashes
        $uri = ltrim($uri, '/');

        // ensure base URL does not end with a slash
        $base = rtrim($this->baseUrl(), '/');

        return $base.'/'.$uri;
    }

    /**
     * Get all possible environment values
     *
     * @return string[] List of environment constants
     */
    public static function values(): array
    {
        return [
            self::SANDBOX,
            self::SIMULATION,
            self::PRODUCTION,
        ];
    }
}
