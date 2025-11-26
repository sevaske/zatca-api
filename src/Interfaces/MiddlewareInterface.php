<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Interfaces;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

interface MiddlewareInterface
{
    public function handle(RequestInterface $request, callable $next): ResponseInterface;
}
