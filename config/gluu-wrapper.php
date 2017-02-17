<?php

return [
    'algorithm' => 'HS256',
    'authorization_endpoint' => 'https://example.com/authorize',
    'token_endpoint' => 'https://example.com/token',
    'userinfo_endpoint' => 'https://example.com/userinfo',
    'clientinfo_endpoint' => 'https://example.com/clientinfo',

    // This client_id and client_secret is used as self-consumed keys.
    'client_id' => 'fake-client-id',
    'client_secret' => 'fake-client-secret',

    'response_type' => 'code',
    'scope' => 'openid',

    'grant_type' => 'authorization_code',
    'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',

    // Set table name to store OpenID credentials
    'table_name' => 'access_tokens',

    // Set table name of user authentication which implements AuthenticatableContract
    'user_table_name' => 'users',

    // Endpoint for user to access
    'route_endpoint' => '/login',

    // Callback routes
    'route_access_token_granted' => '/access_granted',

    // Route for getting user info
    'route_get_user_info' => '/user_info/{access_token}',
];
