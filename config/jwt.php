<?php

use Tymon\JWTAuth\Providers\Auth\Illuminate;
use Tymon\JWTAuth\Providers\JWT\Lcobucci;

return [
    'secret' => env('JWT_SECRET', null),
    'keys' => [
        'public' => env('JWT_PUBLIC_KEY', null),
        'private' => env('JWT_PRIVATE_KEY', null),
        'passphrase' => env('JWT_PASSPHRASE', null),
    ],
    'ttl' => env('JWT_TTL', 60),
    'refresh_ttl' => env('JWT_REFRESH_TTL', 20160),
    'algo' => env('JWT_ALGO', 'HS256'),
    'required_claims' => [
        'iss',
        'iat',
        'exp',
        'nbf',
        'sub',
        'jti',
    ],
    'persistent_claims' => [],
    'lock_subject' => true,
    'leeway' => env('JWT_LEEWAY', 0),
    'blacklist_enabled' => env('JWT_BLACKLIST_ENABLED', true),
    'blacklist_grace_period' => env('JWT_BLACKLIST_GRACE_PERIOD', 0),
    'decrypt_cookies' => false,
    'providers' => [
        'jwt' => Lcobucci::class,
        'auth' => Illuminate::class,
        'storage' => Tymon\JWTAuth\Providers\Storage\Illuminate::class,
    ],
];
