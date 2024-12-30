<?php
namespace app\admin\command\douyin;

use app\services\KeysUtil;
use think\Cache;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Log;
use Throwable;

class AccessToken extends Command
{
    public $appid = 'awk3j12mq8rkyh1u'; // 开发者应用ID
    public $secret = '272f08856efc2f73dcf55383f47afff5'; // 应用密钥
    public $accountId = '7343082414312376371'; // 来客品牌账户
    public $memAccessTokenInfo = 'douyin_access_token_info'; // 存储access token全部信息的缓存key
    public $crmPostUrl = 'https://saas.qikebao.com/api/clue/de81a2b555d5d1c8fa6d7474107147ae'; // 把本地推的数据通知CRM

    public function init()
    {

    }

    protected function configure()
    {
        $this->init();
        $this->setName('douyin:AccessToken')
            ->setDescription('获取token');
    }

    protected function execute(Input $input, Output $output)
    {
        try {
            $this->main();
        } catch (Throwable $e) {
            print_r($e->getFile().'---'.$e->getMessage().'---'.$e->getLine());die;
        }
    }

    /**
     * @return bool
     */
    private function main()
    {
        $a_stime=microtime(true);
        $accessTokenJson = Cache::get(KeysUtil::memAccessTokenInfo());
        if ($accessTokenJson) {
            $accessTokenInfo = json_decode($accessTokenJson, true);
            $timeout = ($accessTokenInfo['time'] + $accessTokenInfo['expires_in']) - time();
            if($accessTokenInfo['access_token'] && $timeout > 600){
                Log::info('access token有效中，请勿频繁操作');
                echo outputResult([
                    'code' => 0,
                    'msg' => 'access token有效中，请勿频繁操作',
                    'data' => [
                        'access_token' => $accessTokenInfo['access_token'],
                    ],
                ]).PHP_EOL;
                return 1;
            }
        }
        $requestUrl = 'https://open.douyin.com/oauth/client_token/';
        $postData = array(
            'client_key' => $this->appid,
            'client_secret' => $this->secret,
            'grant_type' => 'client_credential',
        );
        $responseData = curl_request($requestUrl,'POST',$postData);
        // 记录刷新refresh token的请求日志
        if ($responseData['message'] != 'success' || $responseData['data']['error_code']) {
            // 记录生成 client_token 失败日志
            Log::error("\t生成client_token失败，错误码：" . $responseData['data']['error_code'] . '，错误信息：' . $responseData['data']['description'] . PHP_EOL);
            echo outputResult([
                'code' => 2,
                'msg' => '生成client_token失败，' . $responseData['data']['description'],
            ]).PHP_EOL;
            return 0;
        }
        Cache::rm(KeysUtil::memAccessTokenInfo()); // 删除历史缓存
        // 把新的access token信息写入缓存，有效期-10分钟
        // 增加当前时间，用于后期判断access token是否该刷新的节点
        $responseData['data']['time'] = time();
        Cache::set(KeysUtil::memAccessTokenInfo(), json_encode($responseData['data']), $responseData['data']['expires_in'] - 600); // 重设缓存
        Log::info("\t生成client_token成功，新的access_token：" . $responseData['data']['access_token'] . PHP_EOL);
        echo outputResult([
            'code' => 0,
            'msg' => '生成client_token成功',
            'data' => [
                'access_token' => $responseData['data']['access_token'],
            ]
        ]).PHP_EOL;
        echo '总用时'.(microtime(true)-$a_stime).PHP_EOL;
        return 1;
    }
}