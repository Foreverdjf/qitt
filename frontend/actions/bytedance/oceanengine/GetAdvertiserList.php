<?php

/**
 * 获取纵横组织下账户列表，拿到广告主ID
 * 每30分钟执行一次，/usr/bin/curl https://apis.fengniao.com/index.php?r=bytedance/oceanengine/get_advertiser_list
 */

namespace frontend\actions\bytedance\oceanengine;

use common\plugin\Common;
use common\plugin\Http;
use yii;
use yii\base\Action;
use yii\helpers\Url;

class GetAdvertiserList extends Action
{
    public function run()
    {
        $localAccountIdsInfoJson = Yii::$app->memcache->get($this->controller->memLocalAccountIds);
        $localAccountIdsInfo = json_decode($localAccountIdsInfoJson, true);
        // 2个小时更新一次缓存
        if (time() - $localAccountIdsInfo['time'] < 7200) {
            file_put_contents($this->controller->logFilename, date('Y-m-d H:i:s') . "\t从缓存中读取广告主ids成功，" . json_encode($localAccountIdsInfo['local_account_ids'], JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
            echo Common::outputResult([
                'code' => 0,
                'msg' => '从缓存中读取广告主ids成功',
                'data' => [
                    'local_account_ids' => $localAccountIdsInfo['local_account_ids'],
                ],
            ]);
            exit();
        }
        $accessTokenInfoJson = Yii::$app->memcache->get($this->controller->memAccessTokenInfo);
        $accessTokenInfo = json_decode($accessTokenInfoJson, true);
        $accessToken = $accessTokenInfo['access_token'];
        if (!$accessToken) {
            file_put_contents($this->controller->logFilename, date('Y-m-d H:i:s') . "\taccess token不存在，请先授权或者刷新" . PHP_EOL, FILE_APPEND);
            echo Common::outputResult([
                'code' => 1,
                'msg' => 'access token不存在，请先授权或者刷新',
            ]);
            exit();
        }
        // 获取已授权账户
        $advertiserRequestUrl = 'https://ad.oceanengine.com/open_api/oauth2/advertiser/get/';
        $advertiserParamArr = array(
            'access_token' => $accessToken,
        );
        $advertiserResponseRes = Http::get($advertiserRequestUrl, $advertiserParamArr);
        file_put_contents($this->controller->logFilename, date('Y-m-d H:i:s') . "\t获取已授权账户，返回结果：" . $advertiserResponseRes . PHP_EOL, FILE_APPEND);
        $advertiserResponseData = json_decode($advertiserResponseRes, true);
        if ($advertiserResponseData['code']) {
            file_put_contents($this->controller->logFilename, date('Y-m-d H:i:s') . "\t获取已授权账户失败，错误码：" . $advertiserResponseData['code'] . '，错误信息：' . $advertiserResponseData['message'] . PHP_EOL, FILE_APPEND);
            echo Common::outputResult([
                'code' => 2,
                'msg' => '获取已授权账户失败，' . $advertiserResponseData['message'],
            ]);
            exit();
        }
        $advertiserList = $advertiserResponseData['data']['list'];
        $advertiserId = $advertiserList[0]['advertiser_id'];

        // 获取纵横组织下账户列表
        $advertiserListRequestUrl = 'https://ad.oceanengine.com/open_api/2/customer_center/advertiser/list/';
        $advertiserListParamArr = array(
            'cc_account_id' => $advertiserId,
            'account_source' => 'LOCAL',
            'page' => 1,
            'page_size' => 10
        );
        $advertiserListResponseRes = $this->curlGet($advertiserListRequestUrl, $advertiserListParamArr, $accessToken);
        file_put_contents($this->controller->logFilename, date('Y-m-d H:i:s') . "\t获取纵横组织下账户列表，返回结果：" . $advertiserListResponseRes . PHP_EOL, FILE_APPEND);
        $advertiserListResponseData = json_decode($advertiserListResponseRes, true);
        if ($advertiserListResponseData['code']) {
            file_put_contents($this->controller->logFilename, date('Y-m-d H:i:s') . "\t获取纵横组织下账户列表失败，错误码：" . $advertiserListResponseData['code'] . '，错误信息：' . $advertiserListResponseData['message'] . PHP_EOL, FILE_APPEND);
            echo Common::outputResult([
                'code' => 3,
                'msg' => '获取纵横组织下账户列表失败，' . $advertiserListResponseData['message'],
            ]);
            exit();
        }
        $advertiserList = $advertiserListResponseData['data']['list'];
        $advertiserIdArr = array_column($advertiserList, 'advertiser_name', 'advertiser_id');
        $localAccountIdsInfo = [
            'local_account_ids' => $advertiserIdArr,
            'time' => time(),
        ];
        // 先清理历史缓存
        Yii::$app->memcache->set($this->controller->memLocalAccountIds, null, time() - 1);
        // 把纵横组织下广告主ID写入缓存
        Yii::$app->memcache->set($this->controller->memLocalAccountIds, json_encode($localAccountIdsInfo), 86400);
        file_put_contents($this->controller->logFilename, date('Y-m-d H:i:s') . "\t获取广告主ids成功，" . json_encode($advertiserIdArr, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
        echo Common::outputResult([
            'code' => 0,
            'msg' => '获取广告主ids成功',
            'data' => [
                'local_account_ids' => $advertiserIdArr,
            ],
        ]);
        exit();
    }

    /**
     * Send GET request
     * @return bool|string : Response in JSON format
     */
    public function curlGet($url = '', $getParamArr = [], $accessToken = '')
    {
        $curl = curl_init();
        /* Values of querystring is also in JSON format */
        foreach ($getParamArr as $key => $value) {
            if (is_array($value) && !empty($value)) {
                $getParamArr[$key] = json_encode($value);
            }
        }
        $url = $url . "?" . http_build_query($getParamArr);
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => array(
                "Access-Token: " . $accessToken,
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }

}
