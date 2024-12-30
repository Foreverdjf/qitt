<?php
namespace app\index\controller;

use app\services\KeysUtil;
use think\Cache;
use think\Log;

class Oceanengine extends BaseController
{
    public $appid = '1800896954934284'; // 开发者应用ID
    public $secret = 'c382b654f36ca0a30eb8fcb13b8fc80177ce1e11'; // 应用密钥
    public $state = 'your_custom_params'; // 自定义参数
    public function callback()
    {
        $state = xinput('state', [false]);    // 授权链接时自定义拼装的参数，验证使用
        $authCode = xinput('auth_code', [false]);    // 授权码，用于换取Access-Token
        if ($state != $this->state) {
            Log::error('非法请求');
            return $this->make400Response('非法请求');
        }
        if (!$authCode) {
            Log::error('获取授权码失败');
            return $this->make400Response('获取授权码失败');
        }
        $accessToken = $this->GetAccessToken($authCode);
        if (!$accessToken) {
            Log::error('获取access token失败');
            return $this->make400Response('获取access token失败');
        }
        Log::info("获取access token成功，access token：" . $accessToken);
        return $this->makeResponse('获取access token成功');
    }

    /**
     * 获取Access Token
     * @param string $authCode 授权码
     */
    public function GetAccessToken($authCode = '')
    {
        $requestUrl = 'https://ad.oceanengine.com/open_api/oauth2/access_token/';
        $postData = array(
            'app_id' => $this->appid,
            'secret' => $this->secret,
            'grant_type' => 'auth_code',
            'auth_code' => $authCode,
        );
        $responseRes = curlPostJson($requestUrl, json_encode($postData));
//         $responseRes = '{"code":0,"message":"OK","request_id":"2024060415320790ACA6A52B0A703A9AE0","data":{"access_token":"e8833c1878a758db90af321b46dbe95235c044cb","advertiser_ids":[1800820034541611],"expires_in":86399,"refresh_token":"5a6580974aabc7d416a92eb310bb3c32d72025ef","refresh_token_expires_in":2591999}}';
        // 记录获取access token的请求日志
        Log::info("请求获取access token，返回结果：" . $responseRes);
        $responseData = json_decode($responseRes, true);
        if ($responseData['code']) {
            // 记录获取access token的失败日志
            Log::error("获取access token失败，错误码：" . $responseData['code'] . '，错误信息：' . $responseData['message']);
            return false;
        }
        // 把access token信息写入缓存，有效期-10分钟
        // 增加当前时间，用于后期判断access token是否该刷新的节点
        $responseData['data']['time'] = time();
        Cache::set(KeysUtil::oceanengineAccessTokenInfo(), json_encode($responseData['data']), $responseData['data']['expires_in'] - 600);
        Cache::set(KeysUtil::oceanengineRefreshToken(), $responseData['data']['refresh_token'], $responseData['data']['refresh_token_expires_in'] - 600);
        return $responseData['data']['access_token'];
    }
}