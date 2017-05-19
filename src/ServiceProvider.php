<?php

namespace KWRI\LaravelGluuWrapper;

use Illuminate\Support\ServiceProvider as BaseProvider;
use KWRI\LaravelGluuWrapper\Contracts\Manager as Contract;
use Carbon\Carbon;

class ServiceProvider extends BaseProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/gluu-wrapper.php' => config_path('gluu-wrapper.php'),
        ], 'config');

        $this->publishes([
            __DIR__.'/../database/migrations/' => database_path('migrations')
        ], 'migrations');

        if ( ! $this->app->routesAreCached()) {
            $this->app['router']->get(config('gluu-wrapper.route_endpoint'), function () {
                return redirect($this->app['gluu-wrapper']->getTokenRequester()->generateURI());
            });

            $this->app['router']->get(config('gluu-wrapper.route_get_user_info'), function ($access_token) {
                $userInfoJWE = $this->app['gluu-wrapper']->getUserRequester()->getUserInfo($access_token);

                return response()->json($userInfoJWE);
            });

            $this->app['router']->get(config('gluu-wrapper.route_access_token_granted'), function () {
                $request = $this->app['gluu-wrapper']->getTokenRequester()->getRequest($this->app['request']);

                if (isset($request['code'])) {
                    $accessToken = $this->app['gluu-wrapper']->getTokenRequester()->getAccessToken($request['code'], $request['state']);

                    return response()->json($accessToken);
                }

                return response()->json([ 'error' => 404, 'message' => 'Error' ]);
            });

            $this->app['router']->post(config('gluu-wrapper.route_save_token'), function() {
                $access_token = $this->app['request']->access_token;
                $refresh_token = $this->app['request']->refresh_token;

                if ( $entry = $this->app['db']->table(config('gluu-wrapper.table_name'))->where('access_token', $access_token)->first()) {
                    return response()->json([ 'success' => 200, 'message' => 'Token already saved' ]);
                }

                // We need to save this new token to database
                // so after token refreshed, we will store it to database
                try {
                    // get user info with new token
                    $userInfo = $this->app['gluu-wrapper']->getUserRequester()->getUserInfo($access_token);
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
                        'access_token' => $access_token,
                        'refresh_token' => $refresh_token,
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

                return response()->json([ 'success' => 200, 'message' => 'Token saved' ]);
            });
        }
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/gluu-wrapper.php', 'gluu-wrapper'
        );

        $this->app->singleton(Contract::class, Manager::class);

        $this->app->singleton('gluu-wrapper', function($app) {
            return $app->make(Contract::class);
        });
    }
}
