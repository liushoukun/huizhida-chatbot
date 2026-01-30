<?php

return [

    // 输入延迟
    'inputs_delay'  => env('INPUTS_DELAY', 6),

    // 默认队列
    'queue_default' => env('HUIZHIDA_QUEUE', 'redis'),

    'queue_connections' => [
        'redis' => [
            'connection' => env('HUIZHIDA_QUEUE_REDIS_CONNECTION', env('REDIS_CONNECTION', 'default')),
        ],

    ],


    /*
  |--------------------------------------------------------------------------
  | 预校验规则配置 (Core)
  |--------------------------------------------------------------------------
  */
    'pre_check'         => [
        'transfer_keywords'    => env('TRANSFER_KEYWORDS', '转人工,人工客服,找人工,真人客服,投诉'),
        'vip_direct_transfer'  => env('VIP_DIRECT_TRANSFER', false),
        'max_agent_retries'    => env('MAX_AGENT_RETRIES', 2),
        'agent_timeout'        => env('AGENT_TIMEOUT', 10), // 秒
        'agent_timeout_action' => env('AGENT_TIMEOUT_ACTION', 'transfer_human'),
    ],

    /*
    |--------------------------------------------------------------------------
    | 智能体配置 (Agent)
    |--------------------------------------------------------------------------
    */
    'agent'             => [
        'timeout'     => env('AGENT_TIMEOUT', 30), // 秒
        'retry_delay' => env('AGENT_RETRY_DELAY', 1), // 秒
    ]
];