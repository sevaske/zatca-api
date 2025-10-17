<?php

namespace Sevaske\ZatcaApi\Exceptions;

use Sevaske\Support\Exceptions\ContextableException;
use Sevaske\ZatcaApi\Interfaces\ZatcaExceptionInterface;

class ZatcaException extends ContextableException implements ZatcaExceptionInterface {}
