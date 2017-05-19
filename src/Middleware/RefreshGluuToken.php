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

class RefreshGluuToken extends BaseMiddleware
{
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
        $response = $next($request);
        // check if token is provided or not
        if (! $token = $this->auth->setRequest($request)->getToken()) {
            return $this->respond('tymon.jwt.absent', 'token_not_provided', 400);
        }

        // check if token is expire or not,
        // if it is expired then, we need to refresh that token
        // if not, just pass to the next step
        if ( ! $this->check($token)) {
            try { 
                // generate new token
                $newToken = $this->auth->setRequest($request)->parseTokenAsObject()->refresh();
            } catch (Exception $e) {
                // token is invalid
                return $this->respond('tymon.jwt.invalid', 'token_invalid', 400);
            }

            // We need to save this new token to database
            // so after token refreshed, we will store it to database
            try {
                // get user info with new token
                $userInfo = $this->app['gluu-wrapper']->getUserRequester()->getUserInfo($newToken);
            } catch (\Exception $e) {
                $userInfo = null;
            }

            // if user not exists, then we assume that this new token is invalid
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
                    'access_token' => $newToken,
                    'refresh_token' => $newToken,
                    'expiry_in' => 60 * 60 * 60 * 24 * 365,
                    'client_id' => $userInfo['inum'],
                    'uid' => $uid,
                    'email' => $userInfo['email'],
                    'app_name' => $userInfo['inum'],
                    'company' => $company,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            // send the refreshed token back to the client
            $response->headers->set('Authorization', 'Bearer '.$newToken);
        }
        return $response;
    }

    /**
     * Check DB
     */
    protected function check($token)
    {
        if ( $entry = $this->app['db']->table(config('gluu-wrapper.table_name'))->where('access_token', $token)->first()) {
            $expired = new Carbon($entry->created_at);
            $now = Carbon::now();
            if ($expired->lt($now)) {
                return entry;
            }
            return true;
        }

        return false;
    }
}
