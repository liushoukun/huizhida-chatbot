<?php

namespace HuiZhiDa\Engine\Core\UI\Consoles\Commands;

use Exception;
use HuiZhiDa\Engine\Core\Application\Services\EngineCoreService;
use HuiZhiDa\Core\Application\Services\ConversationApplicationService;
use HuiZhiDa\Core\Domain\Conversation\Contracts\ConversationQueueInterface;
use HuiZhiDa\Core\Domain\Conversation\DTO\Events\ConversationEvent;
use HuiZhiDa\Core\Domain\Conversation\Enums\ConversationQueueType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 处理会话事件队列命令
 */
class ConversationInputQueueCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'huizhida:inputs:queue';

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
        protected EngineCoreService $processorService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle() : int
    {


        $this->maxJobs = (int) 100;

        // 订阅队列
        $this->mq->subscribe(ConversationQueueType::Inputs, function ($eventData) {
            $event = null;


            try {
                $this->info("收到事件: ".json_encode($eventData, JSON_UNESCAPED_UNICODE));
                $event = ConversationEvent::from($eventData);
                Log::withContext([
                    'conversationId' => $event->conversationId,
                ]);

                // 获取会话级分布式锁
                $lockKey = "conversation:lock:{$event->conversationId}";
                $lock    = Cache::lock($lockKey, 3600); // 锁超时时间1小时

                // 等待获取锁，最多等待10分钟
                $lock->block(600, function () use ($event) {
                    // 再次检查是否是最新事件（获取锁后可能已经有新事件）
                    if (!$this->mq->isLastEvent($event)) {
                        $this->info("获取锁后检测到非最新事件，跳过处理");
                        return;
                    }

                    // 处理会话事件
                    $this->processorService->processConversationEvent($event);
                });

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
                    'event' => $event ? $event->toArray() : null,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                // 拒绝消息，重新入队
                if ($event) {
                    $this->mq->nack($event);
                }
            }
        });

        return Command::SUCCESS;
    }
}
