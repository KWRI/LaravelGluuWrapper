<?php

namespace KWRI\LaravelGluuWrapper;

use KWRI\LaravelGluuWrapper\Contracts\TokenRequester as Contract;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Crypt;

class TokenRequester implements Contract
{
    public function generateURI($selfConsuming = false)
    {
        $builder = new JWTBuilder(config('gluu-wrapper.algorithm'));

        $client_id = request()->get('client_id') ?: config('gluu-wrapper.client_id');
        $client_secret = request()->get('client_secret') ?: config('gluu-wrapper.client_secret');

        $clientData = $this->encryptClientData($client_id, $client_secret);

        $claims = [
            "response_type" => config('gluu-wrapper.response_type'),
            "redirect_uri" => url(config('gluu-wrapper.route_access_token_granted')),
            'client_id' => $client_id,
            "scope" => config('gluu-wrapper.scope'),
            "state" => $clientData,
        ];

        $builder->setSecret($client_secret);
        $builder->addPayloads($claims);

        $token = $builder->generate();

        $uri = config('gluu-wrapper.authorization_endpoint') . "?" . http_build_query($claims) . '&request=' . $token;

        return $uri;
    }

    public function getRequest(Request $request)
    {
        return $request->all();
    }

    public function getAccessToken($code, $clientData)
    {
        $data = $this->decryptClientData($clientData);

        $client_id = $data['client_id'];
        $client_secret = $data['client_secret'];

        $client = new Client();
        $builder = new JWTBuilder(config('gluu-wrapper.algorithm'));
        $exp = 86400;
        $endpoint = config('gluu-wrapper.token_endpoint');

        //prepare openID payload
        $builder->addPayloads([
            "iss" => $client_id,
            "sub" => $client_id,
            "aud" => $endpoint,
            "jti" => md5(time()),
            "exp" => time() + $exp,
            "iat" => time()
            // claims => {} cannot use empty claims, if empty don't include it!
        ]);

        //set client secret
        $builder->setSecret($client_secret);

        //generate JWT
        $token = $builder->generate();

        //Make a request to Gluu's token_endpoint using GuzzleHttp
        $res = $client->request('POST', config('gluu-wrapper.token_endpoint'), [
            'verify' => false,
            'form_params' => [
                "grant_type" => config('gluu-wrapper.grant_type'),
                "code" => $code,
                "redirect_uri" => url(config('gluu-wrapper.route_access_token_granted')),
                'client_assertion_type' => config('gluu-wrapper.client_assertion_type'),
                "client_assertion" => $token . ''
            ]
        ]);

        //decode json result, and get the content
        $result = json_decode($res->getBody()->getContents(), true);
        $now = Carbon::now()->format('Y-m-d H:i:s');
        $expiration = $result['expires_in'];

        return $result;
    }

    public function forceRefreshToken($accessToken, $clientId, $clientSecret)
    {
        $builder = new JWTBuilder('HS256');
        $exp = 86400;

        //prepare openID payload
        $builder->addPayloads([
            "iss" => $clientId,
            "sub" => $clientId,
            "aud" => config('gluu-wrapper.token_endpoint'),
            "jti" => md5(time()),
            "exp" => time() + $exp,
            "iat" => time()
            // claims => {} cannot use empty claims, if empty don't include it!
        ]);
        //set client secret
        $builder->setSecret($clientSecret);

        $jwtToken = $builder->generate();

        $payload = array(
            'grant_type' => 'refresh_token',
            'refresh_token' => $accessToken,
            'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
            'client_assertion' => $jwtToken->__toString()
        );
        
        $client = new Client();
        $res = $client->request('POST', config('gluu-wrapper.token_endpoint'), [
            'form_params' => $payload
        ]);

        //decode json result, and get the content
        $result = json_decode($res->getBody()->getContents(), true);
        return $result;
    }

    public function refreshToken($client_id, $refresh_token)
    {
        $client_id = config('gluu-wrapper.client_id');
        $client_secret = config('gluu-wrapper.client_secret');

        $grant_type = config('gluu-wrapper.grant_type_refresh_token');

        $token_params = array(
            'grant_type' => $grant_type,
            'refresh_token' => $refresh_token,
            'client_id' => $client_id,
            'client_secret' => $client_secret,
        );

        $builder = new JWTBuilder('HS256');
        $exp = 86400;

        //prepare openID payload
        $builder->addPayloads([
            "iss" => $client_id,
            "sub" => $client_id,
            "aud" => config('gluu-wrapper.token_endpoint'),
            "jti" => md5(time()),
            "exp" => time() + $exp,
            "iat" => time()
            // claims => {} cannot use empty claims, if empty don't include it!
        ]);

        //set client secret
        $builder->setSecret($client_secret);

        //generate JWT
        $token = $builder->generate();

        $token_params['client_assertion_type'] = 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer';
        $token_params['client_assertion'] = $token.'';

        // Convert token params to string format
        // $token_params = http_build_query($token_params, null, '&');

        //Make a request to Gluu's token_endpoint using GuzzleHttp
        $client = new Client();
        $res = $client->request('POST', config('gluu-wrapper.token_endpoint'), [
            'form_params' => $token_params
        ]);

        //decode json result, and get the content
        $result = json_decode($res->getBody()->getContents(), true);
        return $result;
    }

    public function encryptClientData($client_id, $client_secret)
    {
        $data = $client_id . '~|~' . $client_secret;
        $data = Crypt::encrypt($data);

        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public function decryptClientData($data)
    {
        $data = base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
        $data = explode('~|~', Crypt::decrypt($data));

        return [
            'client_id' => $data[0],
            'client_secret' => $data[1],
        ];
    }
}
