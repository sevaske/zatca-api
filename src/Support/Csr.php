<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Support;

use Sevaske\ZatcaApi\Exception\InvalidArgumentException;

/**
 * @internal
 */
final class Csr
{
    private const PEM_MARKER = '-----BEGIN';

    private function __construct() {}

    /**
     * ZATCA expects the CSR as base64 of the PEM text. Accepts either the PEM text or that base64 value.
     *
     * @throws InvalidArgumentException
     */
    public static function encode(string $csr): string
    {
        $csr = trim($csr);

        if (strpos($csr, self::PEM_MARKER) !== false) {
            return base64_encode($csr);
        }

        $decoded = base64_decode($csr, true);

        if ($decoded !== false && strpos($decoded, self::PEM_MARKER) !== false) {
            return $csr;
        }

        throw new InvalidArgumentException('The CSR must be a PEM-encoded certificate signing request (or base64 of it).');
    }
}
