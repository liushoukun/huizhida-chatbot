<?php

return [

    // 输入延迟
    'inputs_delay' => env('INPUTS_DELAY', 6),

    'queue_default' => env('HUIZHIDA_QUEUE', 'redis'),

    'queue_connections' => [
        'redis' => [
            'connection' => env('HUIZHIDA_QUEUE_REDIS_CONNECTION', env('REDIS_CONNECTION', 'default')),
        ],

    ]
];