<?php

namespace HuiZhiDa\Gateway\Domain\Models;

class UserInfo
{
    public string $channelUserId;
    public string $nickname = '';
    public string $avatar = '';
    public bool $isVip = false;
    public array $tags = [];

    public function toArray(): array
    {
        return [
            'channel_user_id' => $this->channelUserId,
            'nickname' => $this->nickname,
            'avatar' => $this->avatar,
            'is_vip' => $this->isVip,
            'tags' => $this->tags,
        ];
    }
}
