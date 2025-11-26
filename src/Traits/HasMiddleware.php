<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Traits;

use InvalidArgumentException;
use Sevaske\ZatcaApi\Interfaces\MiddlewareInterface;

trait HasMiddleware
{
    /** @var MiddlewareInterface[] */
    protected array $middleware = [];

    /**
     * Replace current middleware with one or multiple provided instances.
     * This method mutates the current object.
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
        $clone->middleware = array_merge($clone->middleware, $this->normalizeMiddleware($middleware));

        return $clone;
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
