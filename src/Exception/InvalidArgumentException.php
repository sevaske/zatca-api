<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Exception;

/**
 * Thrown for invalid input, before any HTTP request is made.
 */
class InvalidArgumentException extends \InvalidArgumentException implements ZatcaException {}
