<?php

namespace Sevaske\ZatcaApi\Exceptions;

use Sevaske\Support\Exceptions\ContextableException;
use Sevaske\ZatcaApi\Interfaces\ZatcaExceptionInterfaces;

class ZatcaException extends ContextableException implements ZatcaExceptionInterfaces {}
