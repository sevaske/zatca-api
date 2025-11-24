<?php

namespace Sevaske\ZatcaApi\Interfaces;

use Psr\Http\Message\RequestInterface;

interface MiddlewareInterface
{
    public function handle(RequestInterface $request, callable $next): ZatcaResponseInterface;
}