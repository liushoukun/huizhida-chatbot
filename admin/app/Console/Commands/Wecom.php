<?php

namespace App\Console\Commands;

use EasyWeChat\Work\Application;
use HuiZhiDa\Core\Domain\Channel\Models\Channel;
use Illuminate\Console\Command;

class Wecom extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:wecom';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $channelId = '670314584515060443';
        $chanel    = Channel::findOrFail($channelId);


        $this->info('Wecom command executed successfully.');

        $config = [
            'corp_id' => $chanel->config['corp_id'],
            'secret'  => $chanel->config['secret'],
            'token'   => $chanel->config['token'],
            'aes_key' => $chanel->config['encoding_aes_key'],
            // // 记得配置suite_id，不然suite_ticket不能自动存储
            // 'suite_id'     => 'ww9f1388bf664xxxxx',
            // 'suite_secret' => 'reuXvCX_5FhDVm_sOslJEHRVxxxxxxx',

            // /**
            //  * 接口请求相关配置，超时时间等，具体可用参数请参考：
            //  * https://github.com/symfony/symfony/blob/5.3/src/Symfony/Contracts/HttpClient/HttpClientInterface.php
            //  */
            // 'http'         => [
            //     'throw'   => true, // 状态码非 200、300 时是否抛出异常，默认为开启
            //     'timeout' => 5.0,
            //     // 'base_uri' => 'https://qyapi.weixin.qq.com/', // 如果你在国外想要覆盖默认的 url 的时候才使用，根据不同的模块配置不同的 uri
            //
            //     'retry' => true, // 使用默认重试配置
            //     //  'retry' => [
            //     //      // 仅以下状态码重试
            //     //      'status_codes' => [429, 500]
            //     //       // 最大重试次数
            //     //      'max_retries' => 3,
            //     //      // 请求间隔 (毫秒)
            //     //      'delay' => 1000,
            //     //      // 如果设置，每次重试的等待时间都会增加这个系数
            //     //      // (例如. 首次:1000ms; 第二次: 3 * 1000ms; etc.)
            //     //      'multiplier' => 3
            //     //  ],
            // ],
        ];

        $app = new Application($config);

        $api = $app->getClient();
        //$token =  $app->getAccessToken();
        // 获取客服列表
        $response = $api->postJson('/cgi-bin/kf/account/list');
        // 获取接待人员
        $response = $api->postJson('/cgi-bin/kf/servicer/list');


        // 获取会话状态
        $response = $api->postJson('/cgi-bin/kf/service_state/get', [
            'open_kfid'       => '',// 客服ID
            'external_userid' => ' ',//外部用户
        ]);
        // 变更会话状态
        $response = $api->postJson('/cgi-bin/kf/service_state/trans', [
            'open_kfid'       => '',// 客服ID,
            'external_userid' => '',//外部用户标识
            'service_state'   => '',//服务状态
            'servicer_userid' => null,// 指定直接人员
        ]);
        dd($response->getContent()); // 这里会抛出异常);
        //
    }
}
