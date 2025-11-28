<?php

declare(strict_types=1);

namespace Tests\Traits;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Sevaske\ZatcaApi\Interfaces\MiddlewareInterface;
use Sevaske\ZatcaApi\Traits\HasMiddleware;

final class HasMiddlewareTest extends TestCase
{
    private function createObjectWithTrait()
    {
        return new class
        {
            use HasMiddleware;
        };
    }

    private function createMiddlewareMock(): MiddlewareInterface
    {
        return $this->createMock(MiddlewareInterface::class);
    }

    public function test_attach_middleware_adds_single_middleware(): void
    {
        $obj = $this->createObjectWithTrait();
        $middleware = $this->createMiddlewareMock();

        $obj->attachMiddleware($middleware);

        $this->assertCount(1, $obj->getMiddleware());
        $this->assertSame($middleware, $obj->getMiddleware()[0]);
    }

    public function test_attach_middleware_adds_multiple_middleware(): void
    {
        $obj = $this->createObjectWithTrait();
        $m1 = $this->createMiddlewareMock();
        $m2 = $this->createMiddlewareMock();

        $obj->attachMiddleware([$m1, $m2]);

        $this->assertCount(2, $obj->getMiddleware());
        $this->assertSame([$m1, $m2], $obj->getMiddleware());
    }

    public function test_set_middleware_replaces_existing_middleware(): void
    {
        $obj = $this->createObjectWithTrait();
        $old = $this->createMiddlewareMock();
        $new = $this->createMiddlewareMock();

        $obj->attachMiddleware($old);
        $obj->setMiddleware($new);

        $this->assertCount(1, $obj->getMiddleware());
        $this->assertSame($new, $obj->getMiddleware()[0]);
    }

    public function test_with_middleware_returns_new_instance(): void
    {
        $obj = $this->createObjectWithTrait();
        $m = $this->createMiddlewareMock();

        $clone = $obj->withMiddleware($m);

        $this->assertNotSame($obj, $clone);
        $this->assertCount(1, $clone->getMiddleware());
        $this->assertSame($m, $clone->getMiddleware()[0]);

        // Original object remains unchanged
        $this->assertEmpty($obj->getMiddleware());
    }

    public function test_without_middleware_returns_new_instance_with_empty_array(): void
    {
        $obj = $this->createObjectWithTrait();
        $obj->attachMiddleware($this->createMiddlewareMock());

        $clone = $obj->withoutMiddleware();

        $this->assertNotSame($obj, $clone);
        $this->assertEmpty($clone->getMiddleware());

        // Original object still has middleware
        $this->assertCount(1, $obj->getMiddleware());
    }

    public function test_normalize_middleware_accepts_single_instance(): void
    {
        $obj = $this->createObjectWithTrait();
        $m = $this->createMiddlewareMock();

        $ref = new \ReflectionMethod($obj, 'normalizeMiddleware');
        $ref->setAccessible(true);

        $result = $ref->invoke($obj, $m);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame($m, $result[0]);
    }

    public function test_normalize_middleware_accepts_array_of_instances(): void
    {
        $obj = $this->createObjectWithTrait();
        $m1 = $this->createMiddlewareMock();
        $m2 = $this->createMiddlewareMock();

        $ref = new \ReflectionMethod($obj, 'normalizeMiddleware');
        $ref->setAccessible(true);

        $result = $ref->invoke($obj, [$m1, $m2]);

        $this->assertSame([$m1, $m2], $result);
    }

    public function test_normalize_middleware_throws_for_invalid_type(): void
    {
        $obj = $this->createObjectWithTrait();
        $invalid = new \stdClass;

        $ref = new \ReflectionMethod($obj, 'normalizeMiddleware');
        $ref->setAccessible(true);

        $this->expectException(InvalidArgumentException::class);
        $ref->invoke($obj, $invalid);
    }

    public function test_normalize_middleware_throws_for_array_with_invalid_element(): void
    {
        $obj = $this->createObjectWithTrait();
        $valid = $this->createMiddlewareMock();
        $invalid = new \stdClass;

        $ref = new \ReflectionMethod($obj, 'normalizeMiddleware');
        $ref->setAccessible(true);

        $this->expectException(InvalidArgumentException::class);
        $ref->invoke($obj, [$valid, $invalid]);
    }
}
