<?php

namespace App\Console\Commands;

use HuiZhiDa\Processor\Application\Services\AgentService;
use HuiZhiDa\Processor\Application\Services\MessageProcessorService;
use HuiZhiDa\Core\Application\Services\ConversationApplicationService;
use HuiZhiDa\Core\Domain\Conversation\DTO\ChannelMessage;
use HuiZhiDa\Core\Domain\Conversation\DTO\ConversationData;
use HuiZhiDa\Core\Domain\Conversation\DTO\Events\ConversationEvent;
use HuiZhiDa\Core\Domain\Conversation\Enums\ContentType;
use Illuminate\Console\Command;
use RedJasmine\Support\Domain\Data\UserData;

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


    public function handle()
    {
        $service =  app(MessageProcessorService::class);
        $event = new ConversationEvent('68a19eb3-3713-4183-afc3-c45785484e10');

        $service->processConversationEvent($event);

    }
    /**
     * Execute the console command.
     */
    public function handles()
    {

        $conversationApplicationService = app(ConversationApplicationService::class);

        $message = $conversationApplicationService->getPendingInputMessages('f3c13f68-f39b-4174-b88d-7439f8af0d1a');
        dd($message);
        $this->agentService     = app(AgentService::class);
        $channelMessage         = new ChannelMessage();
        $channelMessage->sender = UserData::from(['type' => 'user', 'id' => '1111']);
        $channelMessage->setContentData(ContentType::Text, [
            'text' => '你好',
        ]);
        $messages = [
            $channelMessage
        ];

        $conversation                 = new ConversationData();
        $conversation->conversationId = 'api_xxx_66963604136626508o9';
        $conversation->user           = UserData::from(['type' => 'user', 'id' => '1111']);

        $agentId = '670019755161256034';

        $response = $this->agentService->processMessages($messages, $conversation, $agentId);



    }
}
