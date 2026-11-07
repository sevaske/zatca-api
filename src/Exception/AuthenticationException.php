<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Exception;

/**
 * 401 Unauthorized: missing or wrong CSID credentials, e.g. a compliance CSID used for reporting in production.
 */
class AuthenticationException extends ApiException {}
