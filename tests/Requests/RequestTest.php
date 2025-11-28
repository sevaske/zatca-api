<?php

declare(strict_types=1);

namespace Tests\Requests;

use PHPUnit\Framework\TestCase;
use Sevaske\ZatcaApi\Requests\Request;

final class RequestTest extends TestCase
{
    public function test_uri_is_stored_correctly(): void
    {
        $request = new Request('/test', []);
        $this->assertSame('/test', $request->uri());
    }

    public function test_custom_headers_are_merged_with_default(): void
    {
        $request = new Request('/test', [
            'headers' => [
                'X-Custom' => 'ABC',
            ],
        ]);

        $options = $request->options();

        $this->assertSame('ABC', $options['headers']['X-Custom']);
        $this->assertSame('application/json', $options['headers']['Accept']); // default
    }

    public function test_custom_headers_override_default_headers(): void
    {
        $request = new Request('/test', [
            'headers' => [
                'Accept' => 'text/plain',
            ],
        ]);

        $options = $request->options();

        // overridden
        $this->assertSame('text/plain', $options['headers']['Accept']);

        // unchanged defaults
        $this->assertSame('application/json', $options['headers']['Content-Type']);
        $this->assertSame('en', $options['headers']['Accept-Language']);
    }

    public function test_options_array_contains_passed_non_header_options(): void
    {
        $request = new Request('/test', [
            'timeout' => 5,
            'query' => ['a' => 1],
        ]);

        $options = $request->options();

        $this->assertSame(5, $options['timeout']);
        $this->assertSame(['a' => 1], $options['query']);
    }
}
