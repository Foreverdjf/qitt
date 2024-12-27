<?php

/**
 * 授权回调地址
 * https://apis.fengniao.com/index.php?r=bytedance/oceanengine/callback
 */

namespace frontend\actions\bytedance\oceanengine;

use common\plugin\Common;
use common\plugin\Http;
use yii;
use yii\base\Action;
use yii\helpers\Url;

class Callback extends Action
{
    public function run()
    {
        $request = Yii::$app->request;
        $getParams = Yii::$app->request->get();
        $state = $request->get('state');    // 授权链接时自定义拼装的参数，验证使用
        $authCode = $request->get('auth_code');    // 授权码，用于换取Access-Token
        if ($state != $this->controller->state) {
            file_put_contents($this->controller->logFilename, date('Y-m-d H:i:s') . "\t非法请求" . PHP_EOL, FILE_APPEND);
            echo Common::outputResult([
                'code' => 1,
                'msg' => '非法请求',
            ]);
            exit();
        }
        if (!$authCode) {
            file_put_contents($this->controller->logFilename, date('Y-m-d H:i:s') . "\t获取授权码失败" . PHP_EOL, FILE_APPEND);
            echo Common::outputResult([
                'code' => 2,
                'msg' => '获取授权码失败',
            ]);
            exit();
        }
        $accessToken = $this->GetAccessToken($authCode);
        if (!$accessToken) {
            echo Common::outputResult([
                'code' => 3,
                'msg' => '获取access token失败',
            ]);
            exit();
        }
        file_put_contents($this->controller->logFilename, date('Y-m-d H:i:s') . "\t获取access token成功，access token：" . $accessToken . PHP_EOL, FILE_APPEND);
        echo Common::outputResult([
            'code' => 0,
            'msg' => '获取access token成功',
            'data' => [
                'auth_code' => $authCode,
                'access_token' => $accessToken,
            ],
        ]);
        exit();
    }

    /**
     * 获取Access Token
     * @param string $authCode 授权码
     */
    public function GetAccessToken($authCode = '')
    {
        $requestUrl = 'https://ad.oceanengine.com/open_api/oauth2/access_token/';
        $postData = array(
            'app_id' => $this->controller->appid,
            'secret' => $this->controller->secret,
            'grant_type' => 'auth_code',
            'auth_code' => $authCode,
        );
        $responseRes = Http::curlPostJson($requestUrl, json_encode($postData));
        // $responseRes = '{"code":0,"message":"OK","request_id":"2024060415320790ACA6A52B0A703A9AE0","data":{"access_token":"e8833c1878a758db90af321b46dbe95235c044cb","advertiser_ids":[1800820034541611],"expires_in":86399,"refresh_token":"5a6580974aabc7d416a92eb310bb3c32d72025ef","refresh_token_expires_in":2591999}}';
        // 记录获取access token的请求日志
        file_put_contents($this->controller->logFilename, date('Y-m-d H:i:s') . "\t请求获取access token，返回结果：" . $responseRes . PHP_EOL, FILE_APPEND);
        $responseData = json_decode($responseRes, true);
        if ($responseData['code']) {
            // 记录获取access token的失败日志
            file_put_contents($this->controller->logFilename, date('Y-m-d H:i:s') . "\t获取access token失败，错误码：" . $responseData['code'] . '，错误信息：' . $responseData['message'] . PHP_EOL, FILE_APPEND);
            return false;
        }
        // 把access token信息写入缓存，有效期-10分钟
        // 增加当前时间，用于后期判断access token是否该刷新的节点
        $responseData['data']['time'] = time();
        Yii::$app->memcache->set($this->controller->memAccessTokenInfo, json_encode($responseData['data']), $responseData['data']['expires_in'] - 600);
        Yii::$app->memcache->set($this->controller->memRefreshToken, $responseData['data']['refresh_token'], $responseData['data']['refresh_token_expires_in'] - 600);
        return $responseData['data']['access_token'];
    }
}
