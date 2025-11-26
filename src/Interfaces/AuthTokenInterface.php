<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Interfaces;

interface AuthTokenInterface
{
    public function token(): string;
}
