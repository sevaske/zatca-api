<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Sevaske\ZatcaApi\ZatcaAuth;

final class ZatcaAuthTest extends TestCase
{
    private const CERTIFICATE = "-----BEGIN CERTIFICATE-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8A...\n-----END CERTIFICATE-----";

    private const SECRET = 'taina';

    private ZatcaAuth $authToken;

    protected function setUp(): void
    {
        $this->authToken = new ZatcaAuth(self::CERTIFICATE, self::SECRET);
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
        $authToken = new ZatcaAuth($certificate, $secret);

        $expectedToken = base64_encode(base64_encode(trim($certificate)).':'.trim($secret));
        $this->assertSame($expectedToken, (string) $authToken, 'Constructor should trim inputs before encoding');
    }

    public function test_token_method_returns_same_as_string_cast(): void
    {
        $this->assertSame(
            (string) $this->authToken,
            $this->authToken->token(),
            'token() should return the same value as __toString()'
        );
    }

    public function test_certificate_and_secret_are_trimmed_internally(): void
    {
        $auth = new ZatcaAuth(" \n\tCERT \n ", " \t SECRET  ");
        $reflection = new \ReflectionClass($auth);

        $certProp = $reflection->getProperty('certificate');
        $secretProp = $reflection->getProperty('secret');

        $certProp->setAccessible(true);
        $secretProp->setAccessible(true);

        $this->assertSame('CERT', $certProp->getValue($auth));
        $this->assertSame('SECRET', $secretProp->getValue($auth));
    }

    public function test_token_generation_is_deterministic(): void
    {
        $a = new ZatcaAuth(self::CERTIFICATE, self::SECRET);
        $b = new ZatcaAuth(self::CERTIFICATE, self::SECRET);

        $this->assertSame(
            (string) $a,
            (string) $b,
            'Two instances with same input must generate identical tokens'
        );
    }

    public function test_different_inputs_produce_different_tokens(): void
    {
        $a = new ZatcaAuth(self::CERTIFICATE, 'a');
        $b = new ZatcaAuth(self::CERTIFICATE, 'b');

        $this->assertNotSame(
            (string) $a,
            (string) $b,
            'Different secrets must result in different tokens'
        );
    }

    public function test_to_header_contains_only_authorization_header(): void
    {
        $header = $this->authToken->toHeader();

        $this->assertCount(1, $header, 'Header array should contain exactly one key');
        $this->assertArrayHasKey('Authorization', $header);
    }

    public function test_token_encoding_format_is_correct(): void
    {
        $decoded = base64_decode((string) $this->authToken);

        $this->assertNotFalse($decoded, 'Token should be valid base64');

        [$innerBase64, $secret] = explode(':', $decoded, 2);

        $this->assertSame(trim(self::SECRET), $secret);
        $this->assertSame(
            base64_encode(trim(self::CERTIFICATE)),
            $innerBase64,
            'Inner certificate must be base64-encoded'
        );
    }

    public function test_empty_certificate_and_secret_generate_valid_token(): void
    {
        $auth = new ZatcaAuth('', '');

        $this->assertNotEmpty((string) $auth, 'Even empty values must produce a non-empty base64 token');

        $decoded = base64_decode((string) $auth);
        [$inner, $secret] = explode(':', $decoded);

        $this->assertSame(base64_encode(''), $inner);
        $this->assertSame('', $secret);
    }
}
