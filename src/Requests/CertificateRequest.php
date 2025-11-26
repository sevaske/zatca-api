<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Requests;

abstract class CertificateRequest extends Request
{
    public function __construct(string $csr, string $otp)
    {
        parent::__construct($this->uri(), [
            'body' => [
                'csr' => base64_encode($csr),
            ],
            'headers' => [
                'OTP' => $otp,
            ],
        ]);
    }
}
