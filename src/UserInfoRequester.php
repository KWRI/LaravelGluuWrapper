<?php

namespace KWRI\LaravelGluuWrapper;

use KWRI\LaravelGluuWrapper\Contracts\UserInfoRequester as Contract;
use Lcobucci\JWT\Parser;
use GuzzleHttp\Client;

class UserInfoRequester implements Contract
{
    public function getUserInfo($access_token, $type = 'userinfo')
    {
        $parser = new Parser();

        $client = new Client();
        $res = $client->request('GET', config("gluu-wrapper.{$type}_endpoint"), [
            'verify' => false,
            'headers' => [
                'Authorization' => "Bearer {$access_token}"
            ]
        ]);

        $result = $res->getBody()->getContents();
        if ($type == 'userinfo') {
            $token = $parser->parse($result);
            $claims = $token->getClaims();
        } else {
            $claims = json_decode($result, true);
        }

        return empty($claims) ? null : $claims;
    }

    public function getClientInfo($access_token)
    {
        return $this->getUserInfo($access_token, 'clientinfo');
    }
}
