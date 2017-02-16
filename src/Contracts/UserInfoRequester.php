<?php

namespace KWRI\LaravelGluuWrapper\Contracts;

interface UserInfoRequester
{
    public function getUserInfo($access_token);
}
