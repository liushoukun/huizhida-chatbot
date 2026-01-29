<?php

namespace HuiZhiDa\Engine\Channel\UI\Http\Controllers;

use HuiZhiDa\Core\Application\Services\ConversationApplicationService;
use HuiZhiDa\Core\Domain\Channel\Repositories\ChannelRepositoryInterface;
use HuiZhiDa\Engine\Channel\Application\Services\GatewayApplicationService;
use HuiZhiDa\Engine\Channel\Infrastructure\Adapters\AdapterFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CallbackController
{
    public function __construct(
        protected AdapterFactory $adapterFactory,
        protected ConversationApplicationService $conversationApplicationService,
        protected ChannelRepositoryInterface $channelRepository,
        protected GatewayApplicationService $gatewayApplicationService,
    ) {
    }

    /**
     * 健康检查
     *
     * @param  Request  $request
     * @param  string  $channel
     * @param  string  $id
     *
     * @return mixed
     */
    public function health(Request $request, string $channel, string $id)
    {
        return $this->gatewayApplicationService->health($request, $id);
    }


    /**
     * 处理渠道回调
     */
    public function handle(Request $request, string $channel, string $id) : JsonResponse
    {
        return $this->gatewayApplicationService->callback($request, $id);

    }
}
