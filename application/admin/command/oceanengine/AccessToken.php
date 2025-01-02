<?php
namespace app\admin\command\oceanengine;

use app\services\KeysUtil;
use think\Cache;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Log;
use Throwable;

class AccessToken extends Command
{
    public $appid = '1800896954934284'; // 开发者应用ID
    public $secret = 'c382b654f36ca0a30eb8fcb13b8fc80177ce1e11'; // 应用密钥
    public $state = 'your_custom_params'; // 自定义参数
    public $crmPostUrl = 'https://saas.qikebao.com/api/clue/de81a2b555d5d1c8fa6d7474107147ae'; // 把本地推的数据通知CRM

    public function init()
    {

    }

    protected function configure()
    {
        $this->init();
        $this->setName('oceanengine:AccessToken')
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
        $refreshToken = Cache::get(KeysUtil::oceanengineRefreshToken());
        if(empty($refreshToken)){
            Log::error('refresh_token不存在，重新授权');
            echo outputResult([
                    'code' => 1,
                    'msg' => 'refresh_token不存在，重新授权',
                ]).PHP_EOL;
            return false;
        }
        $accessTokenInfoJson = Cache::get(KeysUtil::oceanengineAccessTokenInfo());
        $accessTokenInfo = json_decode($accessTokenInfoJson, true);
        $accessToken = $accessTokenInfo['access_token'];
        $IntervalTime = time() - $accessTokenInfo['time']; // 设置access token到现在的间隔时间
        $timeout = intval($accessTokenInfo['expires_in']) - $IntervalTime; // access token有效期剩余时间
        if ($accessTokenInfo['expires_in'] < 1 || $timeout < 7200) { // 有效期剩余时间大于2小时，则不需要刷新access token
            $timeFlag = true;
        } else {
            $timeFlag = false;
        }
        if ($accessToken && $refreshToken == $accessTokenInfo['refresh_token'] && !$timeFlag) {
            Log::info('access token有效中，请勿频繁操作');
            echo outputResult([
                    'code' => 0,
                    'msg' => 'access token有效中，请勿频繁操作',
                    'data' => [
                        'access_token' => $accessToken,
                    ],
                ]).PHP_EOL;
            return false;
        }
        $requestUrl = 'https://ad.oceanengine.com/open_api/oauth2/refresh_token/';
        $postData = array(
            'app_id' => $this->appid,
            'secret' => $this->secret,
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        );
        $responseRes = curlPostJson($requestUrl, json_encode($postData));
        // 记录刷新refresh token的请求日志
        Log::info("请求刷新refresh token，返回结果：" . $responseRes);
        $responseData = json_decode($responseRes, true);
        if ($responseData['code']) {
            // 记录刷新refresh token的失败日志
            Log::error("刷新refresh token失败，错误码：" . $responseData['code'] . '，错误信息：' . $responseData['message']);
            echo outputResult([
                'code' => 2,
                'msg' => '刷新refresh token失败，' . $responseData['message'],
            ]).PHP_EOL;
            return false;
        }
        Cache::rm(KeysUtil::oceanengineAccessTokenInfo()); // 删除历史缓存
        Cache::rm(KeysUtil::oceanengineRefreshToken()); // 删除历史缓存
        // 把新的access token信息写入缓存，有效期-10分钟
        // 增加当前时间，用于后期判断access token是否该刷新的节点
        $responseData['data']['time'] = time();
        Cache::set(KeysUtil::oceanengineAccessTokenInfo(), json_encode($responseData['data']), $responseData['data']['expires_in'] - 600); // 重设缓存
        Cache::set(KeysUtil::oceanengineRefreshToken(), $responseData['data']['refresh_token'], $responseData['data']['refresh_token_expires_in'] - 600); // 重设缓存
        Log::info("刷新refresh token成功，新的access_token：" . $responseData['data']['access_token']);
        echo outputResult([
            'code' => 0,
            'msg' => '刷新refresh token成功',
            'data' => [
                'access_token' => $responseData['data']['access_token'],
            ],
        ]).PHP_EOL;
        echo '总用时'.(microtime(true)-$a_stime).PHP_EOL;
        return 1;
    }
}