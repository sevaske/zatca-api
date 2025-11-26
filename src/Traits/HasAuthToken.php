<?php

namespace Sevaske\ZatcaApi\Traits;

use Sevaske\ZatcaApi\Interfaces\AuthTokenInterface;

trait HasAuthToken
{
    protected ?AuthTokenInterface $authToken;

    /**
     * @return $this
     */
    public function setAuthToken(?AuthTokenInterface $authToken)
    {
        $this->authToken = $authToken;

        return $this;
    }
}
