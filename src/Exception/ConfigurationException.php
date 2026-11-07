<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Exception;

/**
 * Thrown when the client cannot be configured, e.g. no PSR-18 HTTP client is installed.
 */
class ConfigurationException extends \LogicException implements ZatcaException {}
