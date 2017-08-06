<?php

/*
 * This file is part of Gluu Auth.
 */

namespace KWRI\LaravelGluuWrapper\Middleware;

use KWRI\LaravelGluuWrapper\Services\GluuAuth;
use Carbon\Carbon;
use Tymon\JWTAuth\Http\Middleware\BaseMiddleware;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Foundation\Application;
use Tymon\JWTAuth\Exceptions\JWTException;

class GluuToken extends BaseMiddleware
{
    protected $token;

    /**
     * Create a new BaseMiddleware instance.
     *
     * @param \Illuminate\Contracts\Routing\ResponseFactory  $response
     * @param \Illuminate\Contracts\Events\Dispatcher  $events
     * @param \Tymon\JWTAuth\JWTAuth  $auth
     */
    public function __construct(Dispatcher $events, GluuAuth $auth)
    {
        $this->events = $events;
        $this->auth = $auth;

    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     * @throws Tymon\JWTAuth\Exceptions\JWTException
     */
    public function handle($request, \Closure $next)
    {
        $token = $this->auth->setRequest($request)->parseTokenAsObject();

        $this->setToken($token);

        $this->validateToken($token);

        // attach newest token on response

        $request->attributes->add(['access_token' => $this->token]);
        return $next($request);
    }

    /**
     * Validate token
     *
     * @param string $token
     */
    protected function validateToken($token)
    {
        if ( ! $this->check($token)) {
            $this->saveUserInfo($token, $token);
        }
    }

    /**
     * Save user info from token
     *
     * @param string $access_token
     * @param string $refresh_token
     * @throws Tymon\JWTAuth\Exceptions\JWTException
     */
    protected function saveUserInfo($access_token, $refresh_token)
    {
        $app = app();
        try {
            $userInfo = $app['gluu-wrapper']->getUserRequester()->getUserInfo($access_token);
        } catch (\Exception $e) {
            $userInfo = null;
        }

        if ( ! $userInfo) {
            throw new JWTException('Invalid token', 400);
        }
        $userInfo = array_map(function($claim){
            return $claim->getValue();
        }, $userInfo);

        $uid = $userInfo['persistentId']; // Tweak this with KW ID
        $company = $userInfo['given_name']; // Tweak this with KW Company

        $now = Carbon::now();
        $app['db']->table(config('gluu-wrapper.table_name'))->insert(
            [
                'access_token' => $access_token,
                'refresh_token' => $refresh_token,
                'expiry_in' => 1 * 60 * 60 * 24 * 365,
                'client_id' => $userInfo['inum'],
                'uid' => $uid,
                'email' => $userInfo['email'],
                'app_name' => $userInfo['inum'],
                'company' => $company,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $this->setToken($access_token);
        $this->check($access_token);
    }

    /**
     * Save user info from token
     *
     * @param string $access_token
     * @param string $refresh_token
     * @throws Tymon\JWTAuth\Exceptions\JWTException
     */
    protected function updateUserInfo($id, $access_token, $refresh_token)
    {
        $app = app();
        try {
            $userInfo = $app['gluu-wrapper']->getUserRequester()->getUserInfo($access_token);
        } catch (\Exception $e) {
            $userInfo = null;
        }

        if ( ! $userInfo) {
            throw new JWTException('Invalid token', 400);
        }
        $userInfo = array_map(function($claim){
            return $claim->getValue();
        }, $userInfo);

        $uid = $userInfo['persistentId']; // Tweak this with KW ID
        $company = $userInfo['given_name']; // Tweak this with KW Company

        $now = Carbon::now();
        $app['db']->table(config('gluu-wrapper.table_name'))->where('id', $id)->update(
            [
                'access_token' => $access_token,
                'refresh_token' => $refresh_token,
                'expiry_in' => 1 * 60 * 60 * 24 * 365,
                'client_id' => $userInfo['inum'],
                'uid' => $uid,
                'email' => $userInfo['email'],
                'app_name' => $userInfo['inum'],
                'company' => $company,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $this->setToken($access_token);
        $this->check($access_token);
    }

    /**
     * Check if token is expired or not
     *
     * @param mixed $entry
     */
    protected function isTokenExpired($entry)
    {
        $expired = (new Carbon($entry->created_at))->addSeconds($entry->expiry_in);

        $now = Carbon::now();
        return $expired->lt($now);
    }

    /**
     * Send request to refresh token
     *
     * @param mixed $entry
     */
    protected function refreshToken($entry)
    {
        $newToken = app()['gluu-wrapper']->getTokenRequester()->refreshToken($entry->client_id, $entry->refresh_token);
        return $newToken;
    }

    /**
     * Check DB
     */
    protected function check($token)
    {
        $app = app();
        if ( $entry = $app['db']->table(config('gluu-wrapper.table_name'))->where('access_token', $token)->first()) {

            // token exists
            // check if it is expired
            if ($this->isTokenExpired($entry)) {
                $newToken = $this->refreshToken($entry);
                $this->updateUserInfo($entry->id, $newToken['access_token'], $newToken['refresh_token']);
            }

            if ($relatedUser = $app['db']->table(config('gluu-wrapper.user_table_name'))->where('email', $entry->email)->first()) {
                // Authenticate
                // $app['auth']->login($relatedUser);
            }

            return $entry;
        }

        return null;
    }

    public function setToken($token)
    {
        $this->token = $token;
    }
}
