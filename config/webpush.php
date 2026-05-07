<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Web Push (VAPID)
    |--------------------------------------------------------------------------
    |
    | Generate keys once: php artisan webpush:vapid  (or use npx web-push generate-vapid-keys)
    | Set WEB_PUSH_ENABLED=true after configuring VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY.
    |
    */
    'enabled' => filter_var(env('WEB_PUSH_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    'vapid' => [
        'subject' => env('VAPID_SUBJECT', 'mailto:support@example.com'),
        'public_key' => env('VAPID_PUBLIC_KEY', ''),
        'private_key' => env('VAPID_PRIVATE_KEY', ''),
    ],

];
