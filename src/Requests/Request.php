<?php
declare(strict_types=1);

namespace Sevaske\ZatcaApi\Requests;

use Sevaske\ZatcaApi\Interfaces\RequestInterface;

class Request implements RequestInterface
{
    protected string $uri;

    protected array $options = [];

    public function __construct(string $uri, array $options)
    {
        $this->uri = $uri;
        $this->options = $options;
        $this->options['headers'] = array_merge(
            $this->defaultHeaders(),
            $options['headers'] ?? []
        );
    }

    public function uri(): string
    {
        return $this->uri;
    }

    public function options(): array
    {
        return $this->options;
    }

    public function method(): string
    {
        return 'POST';
    }

    protected function defaultHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Accept-Language' => 'en',
            'Accept-Version' => 'V2',
        ];
    }
}