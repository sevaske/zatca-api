<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Exception;

/**
 * 5xx: ZATCA failed to process the request; it is safe to retry later.
 */
class ServerException extends ApiException {}
