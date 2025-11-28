<?php

declare(strict_types=1);

namespace Tests\Traits;

use PHPUnit\Framework\TestCase;
use Sevaske\ZatcaApi\Interfaces\AuthTokenInterface;
use Sevaske\ZatcaApi\Traits\HasAuthToken;

final class HasAuthTokenTest extends TestCase
{
    public function test_set_auth_token_assigns_token_and_returns_self(): void
    {
        $mockToken = $this->createMock(AuthTokenInterface::class);

        // Create an anonymous class using the HasAuthToken trait
        $obj = new class
        {
            use HasAuthToken;
        };

        // Call setAuthToken and check that it returns $this
        $return = $obj->setAuthToken($mockToken);
        $this->assertSame($obj, $return);

        // Use Reflection to access the protected property
        $ref = new \ReflectionProperty($obj, 'authToken');
        $ref->setAccessible(true);
        $this->assertSame($mockToken, $ref->getValue($obj));
    }

    public function test_set_auth_token_can_accept_null(): void
    {
        // Create an anonymous class using the HasAuthToken trait
        $obj = new class
        {
            use HasAuthToken;
        };

        // Set the token to null
        $obj->setAuthToken(null);

        // Access the protected property using Reflection
        $ref = new \ReflectionProperty($obj, 'authToken');
        $ref->setAccessible(true);
        $this->assertNull($ref->getValue($obj));
    }
}
