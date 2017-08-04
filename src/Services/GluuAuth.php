<?php namespace KWRI\LaravelGluuWrapper\Services;

use Tymon\JWTAuth\JWTAuth;
use Tymon\JWTAuth\Token;
use Illuminate\Http\Request;

class GluuAuth extends JWTAuth
{
    /**
     * @{inherit}
     */
    public function setToken($token)
    {
        $this->token = $token;

        return $this;
    }

    /**
     * Set token as an object
     *
     * @param string $token
     *
     * @return GluuAuth
     */
    public function setTokenObject($token)
    {
        $this->token = new Token($token);

        return $this;
    }

    /**
     * Parse the token from the request and set it as object.
     *
     * @param string $query
     *
     * @return GluuAuth
     */
    public function parseTokenAsObject($method = 'bearer', $header = 'authorization', $query = 'token')
    {
        if (! $token = $this->parseAuthHeader($header, $method)) {
            if (! $token = $this->request->query($query, false)) {
                throw new JWTException('The token could not be parsed from the request', 400);
            }
        }

        return $token;
    }
    public function setRequest(Request $request)
    {
        $this->request = $request;
        return $this;
    }
    public function parseAuthHeader($header = 'authorization', $method = 'bearer')
    {
        $header = $this->request->headers->get($header);
        if (! starts_with(strtolower($header), $method)) {
            return false;
        }
        return trim(str_ireplace($method, '', $header));
    }
}
