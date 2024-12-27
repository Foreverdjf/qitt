<?php

/**
 * 刷新Refresh Token 保持access token活跃有效
 * 每5分钟执行一次，/usr/bin/curl https://apis.fengniao.com/index.php?r=bytedance/douyin/access_token
 */

namespace frontend\actions\bytedance\douyin;

use common\plugin\Common;
use common\plugin\Http;
use yii;
use yii\base\Action;

class AccessToken extends Action
{
    public function run()
    {
        $accessTokenJson = Yii::$app->memcache->get($this->controller->memAccessTokenInfo);
        $accessTokenInfo = json_decode($accessTokenJson, true);
        $timeout = ($accessTokenInfo['time'] + $accessTokenInfo['expires_in']) - time();
        if ($accessTokenInfo['access_token'] && $timeout > 600) {
            file_put_contents($this->controller->logFilename, date('Y-m-d H:i:s') . "\taccess token有效中，请勿频繁操作" . PHP_EOL, FILE_APPEND);
            echo Common::outputResult([
                'code' => 0,
                'msg' => 'access token有效中，请勿频繁操作',
                'data' => [
                    'access_token' => $accessTokenInfo['access_token'],
                ],
            ]);
            exit();
        }
        $requestUrl = 'https://open.douyin.com/oauth/client_token/';
        $postData = array(
            'client_key' => $this->controller->appid,
            'client_secret' => $this->controller->secret,
            'grant_type' => 'client_credential',
        );
        $responseRes = Http::curlPostJson($requestUrl, json_encode($postData));
        // 记录刷新refresh token的请求日志
        file_put_contents($this->controller->logFilename, date('Y-m-d H:i:s') . "\t请求生成client_token，返回结果：" . $responseRes . PHP_EOL, FILE_APPEND);
        $responseData = json_decode($responseRes, true);
        if ($responseData['message'] != 'success' || $responseData['data']['error_code']) {
            // 记录生成 client_token 失败日志
            file_put_contents($this->controller->logFilename, date('Y-m-d H:i:s') . "\t生成client_token失败，错误码：" . $responseData['data']['error_code'] . '，错误信息：' . $responseData['data']['description'] . PHP_EOL, FILE_APPEND);
            echo Common::outputResult([
                'code' => 2,
                'msg' => '生成client_token失败，' . $responseData['data']['description'],
            ]);
            exit();
        }
        Yii::$app->memcache->set($this->controller->memAccessTokenInfo, null, time() - 1); // 删除历史缓存
        // 把新的access token信息写入缓存，有效期-10分钟
        // 增加当前时间，用于后期判断access token是否该刷新的节点
        $responseData['data']['time'] = time();
        Yii::$app->memcache->set($this->controller->memAccessTokenInfo, json_encode($responseData['data']), $responseData['data']['expires_in'] - 600); // 重设缓存
        file_put_contents($this->controller->logFilename, date('Y-m-d H:i:s') . "\t生成client_token成功，新的access_token：" . $responseData['data']['access_token'] . PHP_EOL, FILE_APPEND);
        echo Common::outputResult([
            'code' => 0,
            'msg' => '生成client_token成功',
            'data' => [
                'access_token' => $responseData['data']['access_token'],
            ],
        ]);
        exit();
    }


}
