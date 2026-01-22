<?php

namespace HuiZhiDa\AgentProcessor\Console\Commands;

use HuiZhiDa\AgentProcessor\Application\Services\MessageProcessorService;
use HuiZhiDa\Gateway\Domain\Contracts\MessageQueueInterface;
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
    protected int $maxJobs = 0;

    public function __construct(
        protected MessageQueueInterface $messageQueue,
        protected MessageProcessorService $processorService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $queue = $this->option('queue') ?? config('agent-processor.queue.conversation_events_queue', 'conversation_events');
        $timeout = (int) $this->option('timeout');
        $this->maxJobs = (int) $this->option('max-jobs');

        $this->info("开始消费队列: {$queue}");
        $this->info("阻塞超时: {$timeout}秒");

        // 订阅队列
        $this->messageQueue->subscribe($queue, function ($event) use ($queue) {
            try {
                $this->info("收到事件: " . json_encode($event, JSON_UNESCAPED_UNICODE));
                
                // 处理会话事件
                $this->processorService->processConversationEvent($event);
                
                // 确认消息
                $this->messageQueue->ack($event);
                
                $this->processedCount++;
                $this->info("处理完成，已处理: {$this->processedCount} 个事件");

                // 检查是否达到最大处理数
                if ($this->maxJobs > 0 && $this->processedCount >= $this->maxJobs) {
                    $this->info("达到最大处理数 {$this->maxJobs}，退出");
                    exit(0);
                }

            } catch (\Exception $e) {
                $this->error("处理事件失败: " . $e->getMessage());
                Log::error('Process conversation event failed', [
                    'event' => $event,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
                // 拒绝消息，重新入队
                $this->messageQueue->nack($event);
            }
        });

        return Command::SUCCESS;
    }
}
