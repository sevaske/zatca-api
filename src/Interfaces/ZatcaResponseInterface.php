<?php

namespace Sevaske\ZatcaApi\Interfaces;

use ArrayAccess;
use JsonSerializable;
use Psr\Http\Message\ResponseInterface;

interface ZatcaResponseInterface extends ArrayAccess, JsonSerializable
{
    /**
     * Returns the original raw PSR-7 HTTP response object OR array.
     *
     * @return ResponseInterface|array The raw response.
     */
    public function raw();

    public function getHttpStatusCode(): ?int;

    public function errors(): array;
}
