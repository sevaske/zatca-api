<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Middleware;

use Closure;
use Throwable;

class Pipeline
{
    /** @var mixed The value being passed through the pipeline */
    protected $passable;

    /** @var array<int, callable|object> List of pipes */
    protected array $pipes = [];

    /** @var string Method to call on object pipes */
    protected string $method = 'handle';

    /**
     * Set the object/value to pass through the pipeline.
     */
    public function send($passable)
    {
        $this->passable = $passable;

        return $this;
    }

    /**
     * Set the array of middleware pipes.
     */
    public function through(array $pipes)
    {
        $this->pipes = $pipes;

        return $this;
    }

    /**
     * Set the method to call on object pipes.
     */
    public function via(string $method)
    {
        $this->method = $method;

        return $this;
    }

    /**
     * Run the pipeline with a final destination callback.
     *
     * @throws Throwable
     */
    public function then(Closure $destination)
    {
        $pipeline = array_reduce(
            array_reverse($this->pipes),
            $this->carry(),
            $destination
        );

        return $pipeline($this->passable);
    }

    /**
     * Build a closure that wraps each pipe layer.
     */
    protected function carry(): Closure
    {
        return function ($stack, $pipe) {
            return function ($passable) use ($stack, $pipe) {
                if (is_callable($pipe)) {
                    return $pipe($passable, $stack);
                }

                if (is_object($pipe)) {
                    if (method_exists($pipe, $this->method)) {
                        return $pipe->{$this->method}($passable, $stack);
                    }

                    return $pipe($passable, $stack);
                }

                throw new \RuntimeException('Pipe must be a callable or an object.');
            };
        };
    }
}
