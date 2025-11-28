<?php

declare(strict_types=1);

namespace Tests\Middleware;

use PHPUnit\Framework\TestCase;
use Sevaske\ZatcaApi\Middleware\Pipeline;

final class PipelineTest extends TestCase
{
    public function test_pipeline_with_callable(): void
    {
        $pipeline = new Pipeline;

        $pipeline->send('start')
            ->through([
                function ($passable, $next) {
                    return $next($passable.'-one');
                },
                function ($passable, $next) {
                    return $next($passable.'-two');
                },
            ]);

        $result = $pipeline->then(function ($passable) {
            return $passable.'-end';
        });

        $this->assertEquals('start-one-two-end', $result);
    }

    public function test_pipeline_with_object(): void
    {
        $pipe1 = new class
        {
            public function handle($passable, $next)
            {
                return $next($passable.'-A');
            }
        };

        $pipe2 = new class
        {
            public function handle($passable, $next)
            {
                return $next($passable.'-B');
            }
        };

        $pipeline = new Pipeline;
        $pipeline->send('X')->through([$pipe1, $pipe2]);

        $result = $pipeline->then(fn ($p) => $p.'-Z');

        $this->assertEquals('X-A-B-Z', $result);
    }

    public function test_pipeline_with_custom_method(): void
    {
        $pipe = new class
        {
            public function process($passable, $next)
            {
                return $next($passable.'-custom');
            }
        };

        $pipeline = new Pipeline;
        $pipeline->send('val')->through([$pipe])->via('process');

        $result = $pipeline->then(fn ($p) => $p.'-end');

        $this->assertEquals('val-custom-end', $result);
    }

    public function test_pipeline_throws_for_invalid_pipe(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Pipe must be a callable or an object.');

        $pipeline = new Pipeline;
        $pipeline->send('val')->through([123]);

        $pipeline->then(fn ($p) => $p);
    }

    public function test_pipeline_with_nested_callables(): void
    {
        $pipeline = new Pipeline;

        $pipeline->send('start')
            ->through([
                fn ($p, $next) => $next($p.'-1'),
                fn ($p, $next) => $next($p.'-2'),
                fn ($p, $next) => $p.'-final', // stops the chain
            ]);

        $result = $pipeline->then(fn ($p) => $p.'-end');

        // last pipe overrides destination
        $this->assertEquals('start-1-2-final', $result);
    }

    public function test_pipeline_passable_object_is_modified_by_middlewares(): void
    {
        $context = new class
        {
            public string $value = '';
        };

        $pipeline = new Pipeline;

        $pipeline->send($context)->through([
            function ($ctx, $next) {
                $ctx->value .= 'A';

                return $next($ctx);
            },
            function ($ctx, $next) {
                $ctx->value .= 'B';

                return $next($ctx);
            },
            function ($ctx, $next) {
                $ctx->value .= 'C';

                return $next($ctx);
            },
        ]);

        $pipeline->then(fn ($ctx) => $ctx->value .= 'D');

        $this->assertEquals('ABCD', $context->value);
    }

    public function test_pipeline_passable_array_is_modified(): void
    {
        $context = ['count' => 0];

        $pipeline = new Pipeline;

        $pipeline->send($context)->through([
            function (&$ctx, $next) {
                $ctx['count'] += 1;

                return $next($ctx);
            },
            function (&$ctx, $next) {
                $ctx['count'] += 2;

                return $next($ctx);
            },
        ]);

        $result = $pipeline->then(fn ($ctx) => $ctx['count'] += 3);

        $this->assertEquals(6, $result);
    }
}
