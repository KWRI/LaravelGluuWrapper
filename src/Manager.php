<?php

namespace KWRI\LaravelGluuWrapper;

class Manager
{
    public function getTokenRequester()
    {
        return new TokenRequester();
    }

    public function getUserRequester()
    {
        return new UserInfoRequester();
    }

    public function getSCIMService() {
        return new SCIMService();
    }
}
