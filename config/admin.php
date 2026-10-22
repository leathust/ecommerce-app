<?php

return [
    'name' => env('ADMIN_NAME', 'System Administrator'),
    'username' => env('ADMIN_USERNAME', 'admin'),
    'email' => env('ADMIN_EMAIL', 'admin@example.com'),
    'password' => env('ADMIN_PASSWORD'),

    'login' => [
        'max_attempts' => 5,
        'decay_seconds' => 60,
    ],
];
