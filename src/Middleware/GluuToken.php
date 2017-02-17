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

        if ( ! $this->check($token)) {
            try {
                $userInfo = $this->app['gluu-wrapper']->getUserRequester()->getUserInfo($token);
            } catch (\Exception $e) {
                $userInfo = null;
            }

            if ( ! $userInfo) {
                return $this->respond('tymon.jwt.absent', 'invalid_token', 400);
            }

            $userInfo = array_map(function($claim){
                return $claim->getValue();
            }, $userInfo);

            // @TODO : tweak these field with the real one
            $uid = $userInfo['sub']; // Tweak this with KW ID
            $company = $userInfo['given_name']; // Tweak this with KW Company

            $now = Carbon::now();
            $this->app['db']->table(config('gluu-wrapper.table_name'))->insert(
                [
                    'access_token' => $token,
                    'refresh_token' => $token,
                    'expiry_in' => 300,
                    'client_id' => $userInfo['inum'],
                    'uid' => $uid,
                    'email' => $userInfo['email'],
                    'app_name' => $userInfo['inum'],
                    'company' => $company,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            $this->check($token);
        }

        return $next($request);
    }

    /**
     * Check DB
     */
    protected function check($token)
    {
        if ( $entry = $this->app['db']->table(config('gluu-wrapper.table_name'))->where('access_token', $token)->first()) {
            if ($relatedUser = $this->app['db']->table(config('gluu-wrapper.user_table_name'))->where('email', $entry->email)->first()) {
                // Authenticate
                $this->app['auth']->onceUsingId($relatedUser->id);

                // Done :)
                return $relatedUser;
            }
        }

        return null;
    }
}
