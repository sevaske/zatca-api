<?php

declare(strict_types=1);

namespace Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Sevaske\ZatcaApi\ZatcaEnvironment;

final class ZatcaEnvironmentTest extends TestCase
{
    public function test_valid_environments_do_not_throw_exceptions(): void
    {
        foreach (ZatcaEnvironment::values() as $env) {
            $instance = new ZatcaEnvironment($env);
            $this->assertInstanceOf(ZatcaEnvironment::class, $instance);
            $this->assertSame($env, (string) $instance);
        }
    }

    public function test_invalid_environment_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ZatcaEnvironment('invalid-env');
    }

    public function test_sandbox_base_url_is_correct(): void
    {
        $env = new ZatcaEnvironment(ZatcaEnvironment::SANDBOX);

        $this->assertSame(
            'https://gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal',
            $env->baseUrl()
        );
    }

    public function test_simulation_base_url_is_correct(): void
    {
        $env = new ZatcaEnvironment(ZatcaEnvironment::SIMULATION);

        $this->assertSame(
            'https://gw-fatoora.zatca.gov.sa/e-invoicing/simulation',
            $env->baseUrl()
        );
    }

    public function test_production_base_url_is_correct(): void
    {
        $env = new ZatcaEnvironment(ZatcaEnvironment::PRODUCTION);

        $this->assertSame(
            'https://gw-fatoora.zatca.gov.sa/e-invoicing/core',
            $env->baseUrl()
        );
    }

    public function test_to_string_returns_environment_name(): void
    {
        $env = new ZatcaEnvironment(ZatcaEnvironment::SANDBOX);

        $this->assertSame(
            ZatcaEnvironment::SANDBOX,
            (string) $env
        );
    }

    public function test_url_method_appends_uri_correctly(): void
    {
        $env = new ZatcaEnvironment(ZatcaEnvironment::SANDBOX);

        $this->assertSame(
            'https://gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal/endpoint',
            $env->url('endpoint')
        );
    }

    public function test_url_method_trims_leading_slash(): void
    {
        $env = new ZatcaEnvironment(ZatcaEnvironment::SIMULATION);

        $this->assertSame(
            'https://gw-fatoora.zatca.gov.sa/e-invoicing/simulation/test',
            $env->url('/test')
        );
    }

    public function test_url_method_works_if_base_url_has_trailing_slash(): void
    {
        // Mock with reflection trick: force baseUrl() to return something ending with "/"
        $env = $this->getMockBuilder(ZatcaEnvironment::class)
            ->setConstructorArgs([ZatcaEnvironment::SANDBOX])
            ->onlyMethods(['baseUrl'])
            ->getMock();

        $env->method('baseUrl')->willReturn(
            'https://example.com/base/'
        );

        $this->assertSame(
            'https://example.com/base/test',
            $env->url('test')
        );
    }

    public function test_url_method_handles_empty_uri(): void
    {
        $env = new ZatcaEnvironment(ZatcaEnvironment::PRODUCTION);

        $this->assertSame(
            'https://gw-fatoora.zatca.gov.sa/e-invoicing/core/',
            $env->url('')
        );
    }
}
