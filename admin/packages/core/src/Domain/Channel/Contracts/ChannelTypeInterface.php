<?php

namespace HuiZhiDa\Core\Domain\Channel\Contracts;

use Filament\Forms\Components\Component;
use HuiZhiDa\Core\Domain\Channel\DTO\Member;
use HuiZhiDa\Core\Domain\Channel\DTO\Receptionist;
use HuiZhiDa\Core\Domain\Channel\DTO\ServiceApplication;
use RedJasmine\Support\Domain\Contracts\TypeEnumInterface;

/**
 * 渠道类型接口
 */
interface ChannelTypeInterface extends TypeEnumInterface
{
    /**
     * 获取配置字段集合
     * 返回 Filament 表单组件数组，用于动态显示配置表单
     *
     * @return array<Component>
     */
    public function getConfigFields() : array;

    /**
     * 获取企业成员列表
     *
     * @param  Member[]  $params  查询参数，如分页、筛选等
     *
     * @return array 成员列表数据
     */
    public function getMembers(array $params = []) : array;

    /**
     * 获取企业成员详情
     *
     * @param  string  $memberId  成员ID
     *
     * @return array|null 成员详情数据，不存在返回null
     */
    public function getMemberDetail(string $memberId) : ?Member;

    /**
     * 获取企业部门，返回树形结构
     *
     * @return array 部门树形数据
     */
    public function getDepartmentTree() : array;

    /**
     * 获取客服应用列表
     *
     * @param  array  $params  查询参数，如分页、筛选等
     *
     * @return ServiceApplication[] 应用列表数据
     */
    public function getApplications(array $params = []) : array;

    /**
     * 获取客服应用的接待人员列表
     *
     * @param  string  $applicationId  应用ID
     * @param  array  $params  查询参数，如分页、筛选等
     *
     * @return Receptionist[] 接待人员列表数据
     */
    public function getReceptionists(string $applicationId, array $params = []) : array;

    /**
     * 添加客服应用的接待人员
     *
     * @param  string  $applicationId  应用ID
     * @param  Receptionist  $receptionist  接待人员
     *
     * @return bool 是否添加成功
     */
    public function addReceptionist(string $applicationId, Receptionist $receptionist) : bool;

    /**
     * 删除客服应用的接待人员
     *
     * @param  string  $applicationId  应用ID
     * @param  Receptionist  $receptionist
     *
     * @return bool 是否删除成功
     */
    public function removeReceptionist(string $applicationId, Receptionist $receptionist) : bool;
}
