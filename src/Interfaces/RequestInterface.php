<?php

namespace Sevaske\ZatcaApi\Interfaces;

interface RequestInterface
{
    public function uri(): string;

    public function options(): array;

    public function method(): string;
}