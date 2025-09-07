<?php

return [

    /*
    |--------------------------------------------------------------------------
    | JWT Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for JWT authentication
    |
    */

    'secret' => env('JWT_SECRET', 'your-secret-key'),

    'ttl' => env('JWT_TTL', 60), // Token expiration time in minutes

    'refresh_ttl' => env('JWT_REFRESH_TTL', 20160), // Refresh token expiration time in minutes (2 weeks)

    'algo' => env('JWT_ALGO', 'HS256'), // JWT algorithm

    'required_claims' => [
        'iss',
        'iat',
        'exp',
        'nbf',
        'sub',
        'jti',
    ],

    'persistent_claims' => [
        // Custom claims that persist through refresh
    ],

    'lock_subject' => true,

    'leeway' => env('JWT_LEEWAY', 0),

    'blacklist_enabled' => env('JWT_BLACKLIST_ENABLED', true),

    'blacklist_grace_period' => env('JWT_BLACKLIST_GRACE_PERIOD', 0),

    'decrypt_cookies' => false,

    'providers' => [
        'jwt' => App\Services\JwtService::class,
        'auth' => Illuminate\Auth\AuthServiceProvider::class,
        'storage' => Illuminate\Auth\AuthServiceProvider::class,
    ],

];
