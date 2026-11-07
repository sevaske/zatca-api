<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Exception;

/**
 * Marker interface implemented by every exception thrown by this library.
 *
 * Catch it to handle any library failure in one place.
 */
interface ZatcaException extends \Throwable {}
