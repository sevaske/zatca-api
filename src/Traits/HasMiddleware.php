<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Traits;

use Sevaske\ZatcaApi\Interfaces\MiddlewareInterface;

trait HasMiddleware
{
    /** @var MiddlewareInterface[] */
    protected array $middleware = [];

    /**
     * Attach one or multiple middleware objects.
     *
     * @param  MiddlewareInterface|MiddlewareInterface[]  $middleware
     * @return static
     */
    public function withMiddleware($middleware)
    {
        // normalize to array
        if ($middleware instanceof MiddlewareInterface) {
            $middleware = [$middleware];
        }

        // validate each element
        foreach ($middleware as $m) {
            if (! $m instanceof MiddlewareInterface) {
                throw new \InvalidArgumentException(
                    'Middleware must implement '.MiddlewareInterface::class
                );
            }
        }

        // merge with existing middleware
        $this->middleware = array_merge($this->middleware, $middleware);

        return $this;
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
}
