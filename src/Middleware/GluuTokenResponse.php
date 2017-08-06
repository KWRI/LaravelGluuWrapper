<?php

namespace KWRI\LaravelGluuWrapper\Middleware;

use Closure;
use KWRI\LaravelGluuWrapper\Services\GluuAuth;

class GluuTokenResponse
{
    protected $token;

    /**
     * @param GluuAuth $auth
     */
    public function __construct(GluuAuth $auth)
    {
        $this->auth = $auth;
    }
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        $token = $this->auth->setRequest($request)->parseTokenAsObject();

        $response->header('Authorization', "Bearer " . $request->attributes->get('access_token'));

        return $response;
    }
}
