<?php

/**
 * 获取本地推线索列表
 * 每3分钟执行一次，/usr/bin/curl https://apis.fengniao.com/index.php?r=bytedance/oceanengine/get_life_clue_list
 */

namespace frontend\actions\bytedance\oceanengine;

use common\plugin\Common;
use common\plugin\Http;
use yii;
use yii\base\Action;
use yii\helpers\Url;

class GetLifeClueList extends Action
{
    public function run()
    {
        $localAccountIdsInfoJson = Yii::$app->memcache->get($this->controller->memLocalAccountIds);
        $localAccountIdsInfo = json_decode($localAccountIdsInfoJson, true);
        $localAccountIds = array_keys($localAccountIdsInfo['local_account_ids']);
        if (!is_array($localAccountIds) || empty($localAccountIds)) {
            file_put_contents($this->controller->logFilename, date('Y-m-d H:i:s') . "\t广告主ids不存在，请先获取" . PHP_EOL, FILE_APPEND);
            echo Common::outputResult([
                'code' => 1,
                'msg' => '广告主ids不存在，请先获取',
            ]);
            exit();
        }
        $accessTokenInfoJson = Yii::$app->memcache->get($this->controller->memAccessTokenInfo);
        $accessTokenInfo = json_decode($accessTokenInfoJson, true);
        $accessToken = $accessTokenInfo['access_token'];
        if (!$accessToken) {
            file_put_contents($this->controller->logFilename, date('Y-m-d H:i:s') . "\taccess token不存在，请先授权或者刷新" . PHP_EOL, FILE_APPEND);
            echo Common::outputResult([
                'code' => 2,
                'msg' => 'access token不存在，请先授权或者刷新',
            ]);
            exit();
        }

        // 通过第一页数据获取总页数
        $requestUrl = 'https://api.oceanengine.com/open_api/2/tools/clue/life/get/';
        $pageSize = 100;
        // $startTime = date('Y-m-d H:i:s', strtotime('-60 day'));
        $startTime = date('Y-m-d H:i:s', time() - 3600);
        $postData = array(
            'local_account_ids' => $localAccountIds,
            'start_time' => $startTime,
            'end_time' => date('Y-m-d H:i:s'),
            'page' => 1,
            'page_size' => $pageSize,
        );
        $lifeClueResponseRes = $this->curlPost($requestUrl, $postData, $accessToken);
        file_put_contents($this->controller->logFilename, date('Y-m-d H:i:s') . "\t获取本地推线索列表，第1页，返回结果：" . $lifeClueResponseRes . PHP_EOL, FILE_APPEND);
        $lifeClueResponseData = json_decode($lifeClueResponseRes, true);
        if ($lifeClueResponseData['code']) {
            file_put_contents($this->controller->logFilename, date('Y-m-d H:i:s') . "\t获取本地推线索列表失败，第1页，错误码：" . $lifeClueResponseData['code'] . '，错误信息：' . $lifeClueResponseData['message'] . PHP_EOL, FILE_APPEND);
            echo Common::outputResult([
                'code' => 3,
                'msg' => '获取本地推线索列表失败，第1页，' . $lifeClueResponseData['message'],
            ]);
            exit();
        }
        $pageInfo = $lifeClueResponseData['data']['page_info'];
        if (!$pageInfo['total']) {
            file_put_contents($this->controller->logFilename, date('Y-m-d H:i:s') . "\t获取本地推线索总数为空，退出执行" . PHP_EOL, FILE_APPEND);
            echo Common::outputResult([
                'code' => 0,
                'msg' => '获取本地推线索总数为空，退出执行',
            ]);
            exit();
        }
        $lifeClueList = $lifeClueResponseData['data']['list'];

        // 有多页数据，循环请求
        for ($currentPage = 2; $currentPage <= $pageInfo['page_total']; $currentPage++) {
            $currentPostData = array(
                'local_account_ids' => $localAccountIds,
                'start_time' => $startTime,
                'end_time' => date('Y-m-d H:i:s'),
                'page' => $currentPage,
                'page_size' => $pageSize,
            );
            $currentLifeClueResponseRes = $this->curlPost($requestUrl, $currentPostData, $accessToken);
            file_put_contents($this->controller->logFilename, date('Y-m-d H:i:s') . "\t获取本地推线索列表，第{$currentPage}页，返回结果：" . $currentLifeClueResponseRes . PHP_EOL, FILE_APPEND);
            $currentLifeClueResponseData = json_decode($currentLifeClueResponseRes, true);
            if ($currentLifeClueResponseData['code']) {
                file_put_contents($this->controller->logFilename, date('Y-m-d H:i:s') . "\t获取本地推线索列表失败，第{$currentPage}页，错误码：" . $currentLifeClueResponseData['code'] . '，错误信息：' . $currentLifeClueResponseData['message'] . PHP_EOL, FILE_APPEND);
                echo Common::outputResult([
                    'code' => 3,
                    'msg' => '获取本地推线索列表失败，第' . $currentPage . '页，' . $currentLifeClueResponseData['message'],
                ]);
                continue;
            }
            $lifeClueList = array_merge($lifeClueList, $currentLifeClueResponseData['data']['list']);
        }

        if (!is_array($lifeClueList) || empty($lifeClueList)) {
            file_put_contents($this->controller->logFilename, date('Y-m-d H:i:s') . "\t获取本地推线索列表为空，退出执行" . PHP_EOL, FILE_APPEND);
            echo Common::outputResult([
                'code' => 0,
                'msg' => '获取本地推线索列表为空，退出执行',
            ]);
            exit();
        }
        foreach ($lifeClueList as $key => $value) {
            // 每推送一条数据，记录缓存30天，防止重复推送
            $memClueIdKey = 'crm_clue_id_' . $value['clue_id'];
            if (Yii::$app->memcache->get($memClueIdKey)) {
                $jumpCount++;
                continue;
            }
            $postData = [
                'create_time_detail' => $value['create_time_detail'],
                'name' => $value['name'],
                'advertiser_name' => $value['advertiser_name'],
                'telephone' => $value['telephone'],
                'city_name' => $value['city_name'],
                'address' => $value['address'],
                'remark_dict' => $value['remark_dict'],
                'clue_id' => $value['clue_id'],
                'local_account_id' => $value['local_account_id'],
                'promotion_id' => $value['promotion_id'],
                'promotion_name' => $value['promotion_name'],
                'content_id' => $value['content_id'],
                'tool_id' => $value['tool_id'],
                'gender' => $value['gender'],
                'age' => $value['age'],
                'flow_type' => $value['flow_type'],
                'action_type' => $value['action_type'],
                'leads_page' => $value['leads_page'],
                'clue_type' => $value['clue_type'],
                'order_id' => $value['order_id'],
            ];
            $crmRes = Http::curlPostJson($this->controller->crmPostUrl, json_encode($postData));
            $crmData = json_decode($crmRes, true);
            if ($crmData['code'] != 1) { // 推送失败的跳过，下次再推送
                $errorCount++;
                continue;
            }
            Yii::$app->memcache->set($memClueIdKey, 1, 86400 * 7);
            file_put_contents($this->controller->logFilename, date('Y-m-d H:i:s') . "\t本地推线索推送CRM成功，" . $memClueIdKey . "，" . json_encode($postData, JSON_UNESCAPED_UNICODE) . PHP_EOL . "\tCRM返回结果：" . json_encode($crmData, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
            // sleep(1);
            $milliseconds = 300 * 1000; // 休眠300毫秒
            usleep($milliseconds);
        }
        $remark = '';
        if ($jumpCount) {
            $remark = '，有跳过重复的数据' . $jumpCount . '条';
        } else if ($errorCount) {
            $remark .= '，有推送失败的数据' . $errorCount . '条';
        }
        file_put_contents($this->controller->logFilename, date('Y-m-d H:i:s') . "\t获取本地推线索列表成功，共计{$pageInfo['total']}条，且已推送CRM" . $remark . PHP_EOL, FILE_APPEND);
        echo Common::outputResult([
            'code' => 0,
            'msg' => '获取本地推线索列表成功，共计' . $pageInfo['total'] . '条，且已推送CRM' . $remark,
            'data' => [
                'life_clue_list' => $lifeClueList,
            ],
        ]);
        exit();
    }

    /**
     * Send POST request
     * @param $json_str : Args in JSON format
     * @return bool|string : Response in JSON format
     */
    function curlPost($url = '', $postParamArr = [], $accessToken = '')
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($postParamArr),
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "Access-Token: " . $accessToken,
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }
}
