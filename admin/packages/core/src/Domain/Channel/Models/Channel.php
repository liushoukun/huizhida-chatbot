<?php

namespace HuiZhiDa\Core\Domain\Channel\Models;

use HuiZhiDa\Core\Domain\Agent\Models\Agent;
use HuiZhiDa\Core\Domain\Channel\Models\Enums\ChannelStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use RedJasmine\Project\Domain\Models\Project;
use RedJasmine\Support\Domain\Contracts\OperatorInterface;
use RedJasmine\Support\Domain\Models\Traits\HasOperator;
use RedJasmine\Support\Domain\Models\Traits\HasSnowflakeId;

class Channel extends Model implements OperatorInterface
{
    use HasSnowflakeId;
    use HasOperator;
    use SoftDeletes;

    public $incrementing = false;

    protected $fillable = [
        'app_id',
        'agent_id',
        'channel',
        'config',
        'status',
    ];

    protected $casts = [
        'config' => 'array',

        'status' => ChannelStatus::class,
    ];

    /**
     * 业务方法：启用渠道
     */
    public function enable() : bool
    {
        if ($this->status === ChannelStatus::DISABLED) {
            $this->update(['status' => ChannelStatus::ENABLED]);
            return true;
        }
        return false;
    }

    /**
     * 业务方法：禁用渠道
     */
    public function disable() : bool
    {
        if ($this->status === ChannelStatus::ENABLED) {
            $this->update(['status' => ChannelStatus::DISABLED]);
            return true;
        }
        return false;
    }

    /**
     * 检查是否启用
     */
    public function isEnabled() : bool
    {
        return $this->status === ChannelStatus::ENABLED;
    }

    /**
     * 检查是否禁用
     */
    public function isDisabled() : bool
    {
        return $this->status === ChannelStatus::DISABLED;
    }


    public function app() : BelongsTo
    {
        return $this->belongsTo(Project::class, 'app_id', 'id');
    }

    /**
     * 获取绑定的智能体
     */
    public function agent() : BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }
}
