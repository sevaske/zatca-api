<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Support;

use Sevaske\ZatcaApi\Exception\InvalidArgumentException;

/**
 * @internal
 */
final class Otp
{
    private function __construct() {}

    /**
     * @throws InvalidArgumentException
     */
    public static function normalize(string $otp): string
    {
        $otp = trim($otp);

        if (preg_match('/^\d+$/', $otp) !== 1) {
            throw new InvalidArgumentException('The OTP must contain digits only (generate it in the Fatoora portal).');
        }

        return $otp;
    }
}
