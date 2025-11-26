<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Traits;

use InvalidArgumentException;
use Sevaske\ZatcaApi\Interfaces\MiddlewareInterface;

trait HasMiddleware
{
    /** @var MiddlewareInterface[] List of attached middleware */
    protected array $middleware = [];

    /**
     * Add one or multiple middleware instances to the existing list.
     *
     * @param MiddlewareInterface|MiddlewareInterface[] $middleware
     * @return static
     */
    public function attachMiddleware($middleware)
    {
        return $this->setMiddleware(array_merge(
            $this->middleware,
            $this->normalizeMiddleware($middleware))
        );
    }

    /**
     * Replace current middleware with provided instances.
     * Mutates the current object.
     *
     * @param  MiddlewareInterface|MiddlewareInterface[]  $middleware
     * @return static
     */
    public function setMiddleware($middleware)
    {
        $this->middleware = $this->normalizeMiddleware($middleware);

        return $this;
    }

    /**
     * Return a new instance with one or multiple middleware attached.
     * Preserves immutability of the current instance.
     *
     * @param  MiddlewareInterface|MiddlewareInterface[]  $middleware
     * @return static
     */
    public function withMiddleware($middleware)
    {
        $clone = clone $this;
        $clone->setMiddleware($middleware);

        return $clone;
    }

    /**
     * Return a new instance with no middleware attached.
     *
     * @return static
     */
    public function withoutMiddleware()
    {
        return $this->withMiddleware([]);
    }

    /**
     * Get all attached middleware.
     *
     * @return MiddlewareInterface[]
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * Normalize input to an array and validate each element.
     *
     * @param  MiddlewareInterface|MiddlewareInterface[]  $middleware
     * @return MiddlewareInterface[]
     *
     * @throws InvalidArgumentException If input is invalid
     */
    protected function normalizeMiddleware($middleware): array
    {
        if ($middleware instanceof MiddlewareInterface) {
            $middleware = [$middleware];
        }

        if (! is_array($middleware)) {
            throw new InvalidArgumentException(
                'Middleware must be an instance of '.MiddlewareInterface::class.' or an array of them.'
            );
        }

        foreach ($middleware as $m) {
            if (! $m instanceof MiddlewareInterface) {
                throw new InvalidArgumentException(
                    'Each middleware must implement '.MiddlewareInterface::class
                );
            }
        }

        return $middleware;
    }
}