<?php namespace KWRI\LaravelGluuWrapper\Services;

use Tymon\JWTAuth\JWTAuth;

class GluuAuth extends JWTAuth
{
    /**
     * @{inherit}
     */
    public function setToken($token)
    {
        $this->token = $token;

        return $this;
    }
}
