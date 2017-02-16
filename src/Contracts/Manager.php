<?php

namespace KWRI\LaravelGluuWrapper\Contracts;

interface Manager
{
    public function getTokenRequester();
    public function getUserRequester();
}
