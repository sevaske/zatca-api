<?php

namespace Sevaske\ZatcaApi\Interfaces;

interface RequestBuilderInterface
{
    public function uri(): string;

    public function options(): array;

    public function method(): string;
}