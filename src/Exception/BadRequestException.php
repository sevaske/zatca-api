<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Exception;

/**
 * 400 Bad Request, e.g. an invalid OTP or CSR.
 */
class BadRequestException extends ApiException {}
