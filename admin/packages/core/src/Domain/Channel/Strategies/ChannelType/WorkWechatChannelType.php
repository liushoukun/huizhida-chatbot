<?php

namespace HuiZhiDa\Core\Domain\Channel\Strategies\ChannelType;

use EasyWeChat\Kernel\HttpClient\AccessTokenAwareClient;
use EasyWeChat\Work\Application;
use Filament\Forms\Components\TextInput;
use HuiZhiDa\Core\Domain\Channel\Contracts\ChannelTypeInterface;
use HuiZhiDa\Core\Domain\Channel\DTO\Member;
use HuiZhiDa\Core\Domain\Channel\DTO\Receptionist;
use HuiZhiDa\Core\Domain\Channel\DTO\ReceptionistStatusEnum;
use HuiZhiDa\Core\Domain\Channel\DTO\ReceptionistTypeEnum;
use HuiZhiDa\Core\Domain\Channel\DTO\ServiceApplication;
use InvalidArgumentException;

/**
 * 企业微信渠道类型
 *
 * 调用成员、部门、客服等 API 前须先 {@see setConfig()} 注入渠道配置（corp_id、secret 等）。
 */
class WorkWechatChannelType implements ChannelTypeInterface
{
    protected ?array $config = null;

    public function value(): string
    {
        return 'work-wechat';
    }

    public function label(): string
    {
        return '企业微信';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-chat-bubble-left-right';
    }

    public function color(): ?string
    {
        return 'green';
    }

    public function tips(): ?string
    {
        return null;
    }

    public function disabled(): bool
    {
        return false;
    }

    /**
     * 设置渠道配置（调用成员/部门/客服等 API 前必须调用）
     *
     * @param  array{corp_id: string, secret: string, ...}  $config
     */
    public function setConfig(array $config): self
    {
        $this->config = $config;

        return $this;
    }

    public function getConfigFields(): array
    {
        return [
            TextInput::make('config.corp_id')
                ->label('企业ID')
                ->required()
                ->helperText('企业微信的企业ID'),
            TextInput::make('config.agent_id')
                ->label('应用ID')
                ->required()
                ->helperText('企业微信应用的AgentID'),
            TextInput::make('config.secret')
                ->label('应用Secret')
                ->required()
                ->helperText('企业微信应用的Secret'),
            TextInput::make('config.token')
                ->label('回调Token')
                ->required()
                ->helperText('企业微信回调验证的Token'),
            TextInput::make('config.aes_key')
                ->label('加密Key')
                ->required()
                ->helperText('企业微信消息加解密的EncodingAESKey'),
        ];
    }

    /**
     * 获取请求客户端（自动管理 access_token）
     */
    protected function getClient(): AccessTokenAwareClient
    {
        $this->ensureConfig();
        $app = new Application($this->config);

        return $app->getClient();
    }

    public function getMembers(array $params = []): array
    {
        $cursor = $params['cursor'] ?? '';
        $limit = min((int) ($params['limit'] ?? 1000), 10000);
        $res = $this->getClient()->postJson('/cgi-bin/user/list_id', [
            'cursor' => $cursor,
            'limit' => $limit,
        ]);
        $data = json_decode($res->getContent(), true) ?? [];
        $this->assertOk($data, '获取成员ID列表');
        $deptUser = $data['dept_user'] ?? [];
        $list = [];
        $seen = [];
        foreach ($deptUser as $row) {
            $uid = $row['userid'] ?? '';
            if ($uid === '' || isset($seen[$uid])) {
                continue;
            }
            $seen[$uid] = true;
            $list[] = Member::from(['id' => $uid, 'name' => null]);
        }

        return $list;
    }

    public function getMemberDetail(string $memberId): ?Member
    {
        $res = $this->getClient()->get('/cgi-bin/user/get', ['userid' => $memberId]);
        $data = json_decode($res->getContent(), true) ?? [];
        if (isset($data['errcode']) && $data['errcode'] !== 0) {
            return null;
        }
        $id = $data['userid'] ?? $memberId;
        $name = $data['name'] ?? null;

        return Member::from(compact('id', 'name'));
    }

    public function getDepartmentTree(): array
    {
        $res = $this->getClient()->get('/cgi-bin/department/list');
        $data = json_decode($res->getContent(), true) ?? [];
        $this->assertOk($data, '获取部门列表');
        $rows = $data['department'] ?? [];
        $map = [];
        foreach ($rows as $r) {
            $id = (string) ($r['id'] ?? '');
            $map[$id] = [
                'id' => $id,
                'name' => $r['name'] ?? null,
                'parentid' => (int) ($r['parentid'] ?? 1),
                'children' => [],
            ];
        }
        $roots = [];
        foreach ($map as $id => &$node) {
            $pid = (string) $node['parentid'];
            if (! isset($map[$pid]) || $pid === $id) {
                $roots[] = &$node;
            } else {
                $map[$pid]['children'][] = &$node;
            }
        }
        unset($node);
        $toTree = static function (array $node) use (&$toTree): array {
            $out = ['id' => $node['id'], 'name' => $node['name'], 'children' => []];
            foreach ($node['children'] ?? [] as $ch) {
                $out['children'][] = $toTree($ch);
            }

            return $out;
        };

        return array_map($toTree, $roots);
    }

    public function getApplications(array $params = []): array
    {
        $offset = (int) ($params['offset'] ?? 0);
        $limit = min((int) ($params['limit'] ?? 100), 100);
        $res = $this->getClient()->postJson('/cgi-bin/kf/account/list', [
            'offset' => $offset,
            'limit' => $limit,
        ]);
        $data = json_decode($res->getContent(), true) ?? [];
        $this->assertOk($data, '获取客服账号列表');
        $accounts = $data['account_list'] ?? [];
        $list = [];
        foreach ($accounts as $a) {
            $list[] = ServiceApplication::from([
                'id' => $a['open_kfid'] ?? '',
                'name' => $a['name'] ?? null,
            ]);
        }

        return $list;
    }

    public function getReceptionists(string $applicationId, array $params = []): array
    {
        $res = $this->getClient()->get('/cgi-bin/kf/servicer/list', ['open_kfid' => $applicationId]);
        $data = json_decode($res->getContent(), true) ?? [];
        $this->assertOk($data, '获取接待人员列表');
        $arr = $data['servicer_list'] ?? [];
        $list = [];
        foreach ($arr as $s) {
            if (isset($s['userid'])) {
                $status = (int) ($s['status'] ?? 0);
                $list[] = Receptionist::from([
                    'type' => ReceptionistTypeEnum::Member,
                    'id' => $s['userid'],
                    'status' => $status === 0 ? ReceptionistStatusEnum::Online : ReceptionistStatusEnum::Offline,
                ]);
            } elseif (isset($s['department_id'])) {
                $list[] = Receptionist::from([
                    'type' => ReceptionistTypeEnum::Department,
                    'id' => (string) $s['department_id'],
                    'status' => ReceptionistStatusEnum::Online,
                ]);
            }
        }

        return $list;
    }

    public function addReceptionist(string $applicationId, Receptionist $receptionist): bool
    {
        $body = ['open_kfid' => $applicationId];
        if ($receptionist->type === ReceptionistTypeEnum::Member) {
            $body['userid_list'] = [$receptionist->id];
        } else {
            $body['department_id_list'] = [(int) $receptionist->id];
        }
        $res = $this->getClient()->postJson('/cgi-bin/kf/servicer/add', $body);
        $data = json_decode($res->getContent(), true) ?? [];
        if (isset($data['errcode']) && $data['errcode'] !== 0) {
            return false;
        }
        $results = $data['result_list'] ?? [];
        foreach ($results as $r) {
            if (isset($r['errcode']) && $r['errcode'] !== 0) {
                return false;
            }
        }

        return true;
    }

    public function removeReceptionist(string $applicationId, Receptionist $receptionist): bool
    {
        $body = ['open_kfid' => $applicationId];
        if ($receptionist->type === ReceptionistTypeEnum::Member) {
            $body['userid_list'] = [$receptionist->id];
        } else {
            $body['department_id_list'] = [(int) $receptionist->id];
        }
        $res = $this->getClient()->postJson('/cgi-bin/kf/servicer/del', $body);
        $data = json_decode($res->getContent(), true) ?? [];
        if (isset($data['errcode']) && $data['errcode'] !== 0) {
            return false;
        }
        $results = $data['result_list'] ?? [];
        foreach ($results as $r) {
            if (isset($r['errcode']) && $r['errcode'] !== 0) {
                return false;
            }
        }

        return true;
    }

    protected function ensureConfig(): void
    {
        if ($this->config === null) {
            throw new InvalidArgumentException('WorkWechatChannelType: 请先调用 setConfig() 注入渠道配置后再调用 API。');
        }
        $corpId = $this->config['corp_id'] ?? '';
        $secret = $this->config['secret'] ?? '';
        if ($corpId === '' || $secret === '') {
            throw new InvalidArgumentException('WorkWechatChannelType: 配置缺少 corp_id 或 secret。');
        }
    }

    /**
     * @param  array<string, mixed>  $res
     */
    protected function assertOk(array $res, string $action): void
    {
        $code = (int) ($res['errcode'] ?? 0);
        if ($code !== 0) {
            throw new InvalidArgumentException(
                "企业微信 API {$action} 失败: [{$code}] ".($res['errmsg'] ?? 'unknown')
            );
        }
    }
}
