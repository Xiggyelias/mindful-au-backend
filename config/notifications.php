<?php

return [
    'email' => [
        'enabled' => env('EMAIL_NOTIFICATIONS_ENABLED', false),
        'privacy_safe' => env('EMAIL_NOTIFICATIONS_PRIVACY_SAFE', true),
        'excluded_titles' => [
            'Daily Wellness Tip',
        ],
    ],
];
