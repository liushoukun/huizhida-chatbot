<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Server Configuration
    |--------------------------------------------------------------------------
    */
    'server' => [
        'mode' => env('GATEWAY_MODE', 'debug'), // debug, release
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Configuration
    |--------------------------------------------------------------------------
    */
    'database' => [
        'connection' => env('DB_CONNECTION', 'mysql'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Configuration
    |--------------------------------------------------------------------------
    */
    'redis' => [
        'connection' => env('REDIS_CONNECTION', 'default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    */
    'queue' => [
        'type' => env('GATEWAY_QUEUE_TYPE', 'redis'), // redis, rabbitmq, kafka
        'connection' => env('GATEWAY_QUEUE_CONNECTION', 'redis'),
        'incoming_queue' => env('GATEWAY_INCOMING_QUEUE', 'incoming_messages'),
        'outgoing_queue' => env('GATEWAY_OUTGOING_QUEUE', 'outgoing_messages'),
        'transfer_queue' => env('GATEWAY_TRANSFER_QUEUE', 'transfer_requests'),
    ],
];
