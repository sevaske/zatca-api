<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Credentials;

/**
 * A Cryptographic Stamp Identifier (CSID) issued by ZATCA: the certificate ("binarySecurityToken")
 * and its secret, used as HTTP Basic credentials.
 */
interface Credentials
{
    /**
     * The "binarySecurityToken" exactly as returned by ZATCA (base64 of the base64-encoded certificate).
     */
    public function getBinarySecurityToken(): string;

    public function getSecret(): string;

    /**
     * The X.509 certificate as base64-encoded DER (the value used to sign invoices).
     */
    public function getCertificate(): string;

    /**
     * The X.509 certificate in PEM format.
     */
    public function getCertificatePem(): string;

    /**
     * Value of the Authorization header: "Basic base64(binarySecurityToken:secret)".
     */
    public function getAuthorizationHeader(): string;
}
