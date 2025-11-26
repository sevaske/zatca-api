<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Sevaske\ZatcaApi\AuthToken;

final class AuthTokenTest extends TestCase
{
    private const CERTIFICATE = "-----BEGIN CERTIFICATE-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8A...\n-----END CERTIFICATE-----";

    private const SECRET = 'taina';

    private AuthToken $authToken;

    protected function setUp(): void
    {
        $this->authToken = new AuthToken(self::CERTIFICATE, self::SECRET);
    }

    public function test_token_is_generated_according_to_spec(): void
    {
        $expectedToken = base64_encode(base64_encode(trim(self::CERTIFICATE)).':'.trim(self::SECRET));
        $actualToken = (string) $this->authToken;

        $this->assertSame($expectedToken, $actualToken, 'Token should be correctly base64 encoded');
    }

    public function test_to_basic_returns_correct_format(): void
    {
        $expected = 'Basic '.(string) $this->authToken;
        $actual = $this->authToken->toBasic();

        $this->assertSame($expected, $actual, 'toBasic() should prepend "Basic " to the token');
    }

    public function test_to_header_returns_correct_authorization_header(): void
    {
        $expected = ['Authorization' => $this->authToken->toBasic()];
        $actual = $this->authToken->toHeader();

        $this->assertIsArray($actual, 'toHeader() should return an array');
        $this->assertArrayHasKey('Authorization', $actual, 'Header array should have "Authorization" key');
        $this->assertSame($expected, $actual, 'Header array should contain correct Authorization header');
    }

    public function test_string_casting_returns_same_value_as_to_basic_without_prefix(): void
    {
        $tokenString = (string) $this->authToken;
        $toBasic = $this->authToken->toBasic();

        $this->assertStringStartsWith('Basic ', $toBasic, 'toBasic() string should start with "Basic "');
        $this->assertSame(substr($toBasic, 6), $tokenString, 'The token part of toBasic() should equal __toString() output');
    }

    public function test_constructor_trims_input_strings(): void
    {
        $certificate = '  '.self::CERTIFICATE.'  ';
        $secret = '  '.self::SECRET.'  ';
        $authToken = new AuthToken($certificate, $secret);

        $expectedToken = base64_encode(base64_encode(trim($certificate)).':'.trim($secret));
        $this->assertSame($expectedToken, (string) $authToken, 'Constructor should trim inputs before encoding');
    }
}
