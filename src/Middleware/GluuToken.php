<?php

/*
 * This file is part of Gluu Auth.
 */

namespace KWRI\LaravelGluuWrapper\Middleware;

use KWRI\LaravelGluuWrapper\Services\GluuAuth;
use Carbon\Carbon;
use Tymon\JWTAuth\Middleware\BaseMiddleware;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Foundation\Application;

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
    public function __construct(ResponseFactory $response, Dispatcher $events, GluuAuth $auth, Application $app)
    {
        $this->response = $response;
        $this->events = $events;
        $this->auth = $auth;

        $this->app = $app;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, \Closure $next)
    {
        if (! $token = $this->auth->setRequest($request)->getToken()) {
            return $this->respond('tymon.jwt.absent', 'token_not_provided', 400);
        }

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
     */
    protected function saveUserInfo($access_token, $refresh_token)
    {
        try {
            $userInfo = $this->app['gluu-wrapper']->getUserRequester()->getUserInfo($access_token);
        } catch (\Exception $e) {
            $userInfo = null;
        }

        if ( ! $userInfo) {
            return $this->respond('tymon.jwt.absent', 'invalid_token', 400);
        }
        $userInfo = array_map(function($claim){
            return $claim->getValue();
        }, $userInfo);

        $uid = $userInfo['persistentId']; // Tweak this with KW ID
        $company = $userInfo['given_name']; // Tweak this with KW Company

        $now = Carbon::now();
        $this->app['db']->table(config('gluu-wrapper.table_name'))->insert(
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
        $newToken = $this->app['gluu-wrapper']->getTokenRequester()->refreshToken($entry->client_id, $entry->refresh_token);
        return $newToken;
    }

    /**
     * Check DB
     */
    protected function check($token)
    {
        if ( $entry = $this->app['db']->table(config('gluu-wrapper.table_name'))->where('access_token', $token)->first()) {

            // token exists
            // check if it is expired
            if ($this->isTokenExpired($entry)) {
                $newToken = $this->refreshToken($entry);
                $this->saveUserInfo($newToken['access_token'], $newToken['refresh_token']);
            }

            if ($relatedUser = $this->app['db']->table(config('gluu-wrapper.user_table_name'))->where('email', $entry->email)->first()) {
                // Authenticate
                $this->app['auth']->onceUsingId($relatedUser->id);
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
