<?php

namespace HuiZhiDa\AgentProcessor\Console\Commands;

use Exception;
use HuiZhiDa\AgentProcessor\Application\Services\MessageProcessorService;
use HuiZhiDa\Core\Application\Services\ConversationApplicationService;
use HuiZhiDa\Core\Domain\Conversation\Contracts\ConversationQueueInterface;
use HuiZhiDa\Core\Domain\Conversation\DTO\Events\ConversationEvent;
use HuiZhiDa\Core\Domain\Conversation\Enums\ConversationQueueType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * 处理会话事件队列命令
 */
class ProcessConversationEventsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'agent-processor:consume 
                            {--queue= : 队列名称}
                            {--timeout=5 : 阻塞超时时间（秒）}
                            {--max-jobs=0 : 最大处理任务数，0表示无限制}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '消费会话事件队列，处理未处理消息';

    protected int $processedCount = 0;
    protected int $maxJobs        = 0;

    public function __construct(
        protected ConversationApplicationService $conversationApplicationService,

        protected ConversationQueueInterface $mq,
        protected MessageProcessorService $processorService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle() : int
    {
        $queue = ConversationQueueType::Processor->getQueueName();

        $timeout       = (int) $this->option('timeout');
        $this->maxJobs = (int) $this->option('max-jobs');

        $this->info("开始消费队列: {$queue}");
        $this->info("阻塞超时: {$timeout}秒");


        // 订阅队列
        $this->mq->subscribe(ConversationQueueType::Processor, function ($eventData) use ($queue) {

            try {

                $this->info("收到事件: ".json_encode($eventData, JSON_UNESCAPED_UNICODE));
                $event = ConversationEvent::from($eventData);
                Log::withContext([
                    'conversationId' => $event->conversationId,
                ]);
                // 防抖处理，只处理最后一次
                if (!$this->mq->isLastEvent($event)) {
                    $this->info("非最后一次事件，忽略");
                    return;
                }

                // 处理会话事件
                $this->processorService->processConversationEvent($event);

                // 确认消息
                $this->mq->ack($event);

                $this->processedCount++;
                $this->info("处理完成，已处理: {$this->processedCount} 个事件");

                // 检查是否达到最大处理数
                if ($this->maxJobs > 0 && $this->processedCount >= $this->maxJobs) {
                    $this->info("达到最大处理数 {$this->maxJobs}，退出");
                    exit(0);
                }

            } catch (Exception $e) {
                $this->error("处理事件失败: ".$e->getMessage());
                Log::error('Process conversation event failed', [
                    'event' => $event,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                // 拒绝消息，重新入队
                $this->mq->nack($event);
            }
        });

        return Command::SUCCESS;
    }
}
