<?php

$idpEndpoint = env('IDP_ENDPOINT', 'https://dev.idp.kw.com');

return [
    'algorithm' => 'HS256',
    'authorization_endpoint' => "{$idpEndpoint}/oxauth/seam/resource/restv1/oxauth/authorize",
    'token_endpoint' => "{$idpEndpoint}/oxauth/seam/resource/restv1/oxauth/token",
    'userinfo_endpoint' => "{$idpEndpoint}/oxauth/seam/resource/restv1/oxauth/userinfo",
    'clientinfo_endpoint' => "{$idpEndpoint}/oxauth/seam/resource/restv1/oxauth/clientinfo",
    'scim_endpoint' => "{$idpEndpoint}/identity/seam/resource/restv1/scim/v2",
    'scim_token' => env('SCIM_TOKEN'),

    // This client_id and client_secret is used as self-consumed keys.
    'client_id' => env('IDP_CLIENT_ID', '@!8EF4.0267.10A3.7789!0001!58DE.5ADC!0008!C2C4.9504'),
    'client_secret' => env('IDP_CLIENT_SECRET', 'r3f4ct0ry'),

    'response_type' => 'code',
    'scope' => 'openid email profile',

    'grant_type' => 'authorization_code',
    'grant_type_refresh_token' => 'refresh_token',
    'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',

    // Set this value to true to automatically store your token.
    // Make sure that you have migrate the table.
    'autosave' => true,
    'table_name' => 'access_tokens',
    'user_table_name' => 'users',

    // Endpoint for user to access
    'route_endpoint' => '/api/v1/login',

    // Callback routes
    'route_access_token_granted' => '/callback',
    'route_save_token' => '/save_token'
];
