<?php

namespace HuiZhiDa\Core\Domain\Agent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use HuiZhiDa\Core\Domain\Agent\Models\Enums\AgentStatus;
use RedJasmine\Support\Domain\Contracts\OperatorInterface;
use RedJasmine\Support\Domain\Contracts\OwnerInterface;
use RedJasmine\Support\Domain\Models\Traits\HasOperator;
use RedJasmine\Support\Domain\Models\Traits\HasOwner;
use RedJasmine\Support\Domain\Models\Traits\HasSnowflakeId;

class Agent extends Model implements OperatorInterface, OwnerInterface
{
    use HasSnowflakeId;
    use HasOwner;
    use HasOperator;
    use SoftDeletes;

    public $incrementing = false;

    protected $fillable = [
        'name',
        'agent_type',
        'provider',
        'config',
        'fallback_agent_id',
        'status',
    ];

    protected $casts = [
        'config' => 'array',
        'status' => AgentStatus::class,
    ];

    /**
     * 获取降级智能体
     */
    public function fallbackAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'fallback_agent_id');
    }

    /**
     * 获取作为降级智能体的所有智能体
     */
    public function agentsUsingAsFallback(): HasMany
    {
        return $this->hasMany(Agent::class, 'fallback_agent_id');
    }

    /**
     * 业务方法：启用智能体
     */
    public function enable(): bool
    {
        if ($this->status === AgentStatus::DISABLED) {
            $this->update(['status' => AgentStatus::ENABLED]);
            return true;
        }
        return false;
    }

    /**
     * 业务方法：禁用智能体
     */
    public function disable(): bool
    {
        if ($this->status === AgentStatus::ENABLED) {
            $this->update(['status' => AgentStatus::DISABLED]);
            return true;
        }
        return false;
    }

    /**
     * 检查是否启用
     */
    public function isEnabled(): bool
    {
        return $this->status === AgentStatus::ENABLED;
    }

    /**
     * 检查是否禁用
     */
    public function isDisabled(): bool
    {
        return $this->status === AgentStatus::DISABLED;
    }

}
