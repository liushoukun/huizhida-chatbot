<?php

namespace App\Console\Commands;

use HuiZhiDa\AgentProcessor\Application\Services\AgentService;
use Illuminate\Console\Command;

class AgentTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:agent-test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->agentService = app(AgentService::class);

        $messages     = [
            [
                'content' => [
                    'text' => '你好呀'
                ],
            ]
        ];
        $conversation = [
            'conversation_id'       => 'api_xxx_669636041366265080',
            'agent_conversation_id' => null,
        ];
        $agentId      = '669636864175210494';
        $response     = $this->agentService->processMessages($messages, $conversation, $agentId);
        dd($response);
    }
}
