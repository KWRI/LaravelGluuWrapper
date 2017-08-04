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
        try {
            $res = $client->request('GET', config("gluu-wrapper.{$type}_endpoint"), [
                'verify' => false,
                'headers' => [
                    'Authorization' => "Bearer {$access_token}"
                ]
            ]);

            $result = $res->getBody()->getContents();

            $token = $parser->parse($result);

            $claims = $token->getClaims();

            return empty($claims) ? null : $claims;
        } catch (\Exception $e) {
            dd($e);
        }
    }

    public function getClientInfo($access_token)
    {
        return $this->getUserInfo($access_token, 'clientinfo');
    }
}
