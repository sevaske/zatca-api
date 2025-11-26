<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Interfaces;

use Psr\Http\Message\RequestInterface;

interface MiddlewareInterface
{
    public function handle(RequestInterface $request, callable $next): ZatcaResponseInterface;
}
