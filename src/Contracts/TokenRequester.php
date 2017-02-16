<?php

namespace KWRI\LaravelGluuWrapper\Contracts;

use Illuminate\Http\Request;

interface TokenRequester
{
    public function generateURI($selfConsuming = false);
    public function getRequest(Request $request);
    public function getAccessToken($code, $clientData);
}
