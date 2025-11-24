<?php

namespace Sevaske\ZatcaApi\Traits;

use Sevaske\ZatcaApi\Interfaces\AuthTokenInterface;

trait HasAuthToken
{
    protected ?AuthTokenInterface $authToken;

    /**
     * @param AuthTokenInterface|null $authToken
     *
     * @return $this
     */
    public function withAuthToken(?AuthTokenInterface $authToken)
    {
        $this->authToken = $authToken;

        return $this;
    }
}