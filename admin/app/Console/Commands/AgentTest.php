<?php

namespace App\Console\Commands;

use HuiZhiDa\Core\Domain\Agent\Models\Agent;
use HuiZhiDa\Core\Domain\Conversation\DTO\AgentMessage;
use HuiZhiDa\Core\Domain\Conversation\DTO\Contents\MarkdownContent;
use HuiZhiDa\Core\Domain\Conversation\Enums\MessageType;
use HuiZhiDa\Engine\Agent\Application\Services\AgentService;
use HuiZhiDa\Engine\Agent\Domain\Data\AgentChatRequest;
use HuiZhiDa\Engine\Agent\Infrastructure\Adapters\AgentAdapterFactory;
use HuiZhiDa\Engine\Core\Application\Services\MessageProcessorService;
use HuiZhiDa\Core\Application\Services\ConversationApplicationService;
use HuiZhiDa\Core\Domain\Conversation\DTO\ChannelMessage;
use HuiZhiDa\Core\Domain\Conversation\DTO\ConversationData;
use HuiZhiDa\Core\Domain\Conversation\DTO\Events\ConversationEvent;
use HuiZhiDa\Core\Domain\Conversation\Enums\ContentType;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
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
        $factory = new AgentAdapterFactory();
        $agent   = Agent::find(670019755161256034);

        $adapter     = $factory->create($agent);
        $chatRequest = new AgentChatRequest();

        $message              = new ChannelMessage();
        $message->messageType = MessageType::Chat;
        $message->setContentData(ContentType::Text, ['text' => '你好']);
        $chatRequest->messages = [$message];
        $chatRequest->user = UserData::from([
            'type' => 'user',
            'id'   => '1111',
        ]);
        $chatRequest->conversationId = Str::random();
        $adapter->chat($chatRequest);


    }

    public function handle33()
    {
        $content       = new MarkdownContent();
        $content->text = <<<DOC
- 测试
![通用取件码](https://lf6-appstore-sign.oceancloudapi.com/ocean-cloud-tos/FileBizType.BIZ_BOT_DATASET/3836665526485704_1769654517791542783_4VFs2PhxJh.jpg?x-expires=1772247227&x-signature=WcPQI7Dm9Vvzs%2BxvFDjQ2JaqEuA%3D)
DOC;

        //dd($content->getPlainText());
        dd($content->getMediaAttachments());


    }


    public function handle233()
    {
        $service = app(MessageProcessorService::class);
        $event   = new ConversationEvent('68a19eb3-3713-4183-afc3-c45785484e10');

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
