<?php

/**
 * 刷新Refresh Token 保持access token活跃有效
 * 每30分钟执行一次，/usr/bin/curl https://apis.fengniao.com/index.php?r=bytedance/oceanengine/access_token
 */

namespace frontend\actions\bytedance\oceanengine;

use common\plugin\Common;
use common\plugin\Http;
use yii;
use yii\base\Action;
use yii\helpers\Url;

class AccessToken extends Action
{
    public function run()
    {
        $refreshToken = Yii::$app->memcache->get($this->controller->memRefreshToken);
        $accessTokenInfoJson = Yii::$app->memcache->get($this->controller->memAccessTokenInfo);
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
            file_put_contents($this->controller->logFilename, date('Y-m-d H:i:s') . "\taccess token有效中，请勿频繁操作" . PHP_EOL, FILE_APPEND);
            echo Common::outputResult([
                'code' => 0,
                'msg' => 'access token有效中，请勿频繁操作',
                'data' => [
                    'access_token' => $accessToken,
                ],
            ]);
            exit();
        }
        if (!$refreshToken) {
            file_put_contents($this->controller->logFilename, date('Y-m-d H:i:s') . "\trefresh_token不存在，请重新授权" . PHP_EOL, FILE_APPEND);
            echo Common::outputResult([
                'code' => 1,
                'msg' => 'refresh_token不存在，请重新授权',
            ]);
            exit();
        }
        $requestUrl = 'https://ad.oceanengine.com/open_api/oauth2/refresh_token/';
        $postData = array(
            'app_id' => $this->controller->appid,
            'secret' => $this->controller->secret,
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        );
        $responseRes = Http::curlPostJson($requestUrl, json_encode($postData));
        // 记录刷新refresh token的请求日志
        file_put_contents($this->controller->logFilename, date('Y-m-d H:i:s') . "\t请求刷新refresh token，返回结果：" . $responseRes . PHP_EOL, FILE_APPEND);
        $responseData = json_decode($responseRes, true);
        if ($responseData['code']) {
            // 记录刷新refresh token的失败日志
            file_put_contents($this->controller->logFilename, date('Y-m-d H:i:s') . "\t刷新refresh token失败，错误码：" . $responseData['code'] . '，错误信息：' . $responseData['message'] . PHP_EOL, FILE_APPEND);
            echo Common::outputResult([
                'code' => 2,
                'msg' => '刷新refresh token失败，' . $responseData['message'],
            ]);
            exit();
        }
        Yii::$app->memcache->set($this->controller->memAccessTokenInfo, null, time() - 1); // 删除历史缓存
        Yii::$app->memcache->set($this->controller->memRefreshToken, null, time() - 1); // 删除历史缓存

        // 把新的access token信息写入缓存，有效期-10分钟
        // 增加当前时间，用于后期判断access token是否该刷新的节点
        $responseData['data']['time'] = time();
        Yii::$app->memcache->set($this->controller->memAccessTokenInfo, json_encode($responseData['data']), $responseData['data']['expires_in'] - 600); // 重设缓存
        Yii::$app->memcache->set($this->controller->memRefreshToken, $responseData['data']['refresh_token'], $responseData['data']['refresh_token_expires_in'] - 600);
        file_put_contents($this->controller->logFilename, date('Y-m-d H:i:s') . "\t刷新refresh token成功，新的access_token：" . $responseData['data']['access_token'] . PHP_EOL, FILE_APPEND);
        echo Common::outputResult([
            'code' => 0,
            'msg' => '刷新refresh token成功',
            'data' => [
                'access_token' => $responseData['data']['access_token'],
            ],
        ]);
        exit();
    }


}
