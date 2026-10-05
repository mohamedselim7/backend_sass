<?php

return [
    // Falls back to the null driver until Pusher credentials exist, so the app
    // boots on a fresh clone without live updates crashing it.
    'default' => env('BROADCAST_CONNECTION', env('PUSHER_APP_KEY') ? 'pusher' : 'null'),

    'connections' => [
        'pusher' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'app_id' => env('PUSHER_APP_ID'),
            'options' => [
                'cluster' => env('PUSHER_APP_CLUSTER', 'eu'),
                'useTLS' => true,
            ],
        ],
        'log' => ['driver' => 'log'],
        'null' => ['driver' => 'null'],
    ],
];
