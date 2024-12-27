<?php

/**
 * 获取抖音来客-客资中心线索
 * 线索查询
 * 每6分钟执行一次，/usr/bin/curl https://apis.fengniao.com/index.php?r=bytedance/douyin/get_local_life_list
 * https://developer.open-douyin.com/docs/resource/zh-CN/local-life/develop/OpenAPI/clue-management/clue-query
 */

namespace frontend\actions\bytedance\douyin;

use common\plugin\Common;
use common\plugin\Http;
use yii;
use yii\base\Action;

class GetLocalLifeList extends Action
{
    public function run()
    {
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
        $requestUrl = 'https://open.douyin.com/goodlife/v1/open_api/crm/clue/query/';
        $pageSize = 100;
        // $startTime = date('Y-m-d H:i:s', strtotime('-30 day'));
        $startTime = date('Y-m-d H:i:s', time() - 3600);
        $getData = array(
            'account_id' => $this->controller->accountId,
            'start_time' => $startTime,
            'end_time' => date('Y-m-d H:i:s', time() - 600),
            'page' => 1,
            'page_size' => $pageSize,
        );
        $lifeClueResponseRes = $this->curlGet($requestUrl, $getData, $accessToken);
        file_put_contents($this->controller->logFilename, date('Y-m-d H:i:s') . "\t获取线索查询列表，第1页，返回结果：" . $lifeClueResponseRes . PHP_EOL, FILE_APPEND);
        $lifeClueResponseData = json_decode($lifeClueResponseRes, true);
        if ($lifeClueResponseData['extra']['error_code']) {
            file_put_contents($this->controller->logFilename, date('Y-m-d H:i:s') . "\t获取线索查询列表失败，第1页，错误码：" . $lifeClueResponseData['extra']['error_code'] . '，错误信息：' . $lifeClueResponseData['extra']['description'] . PHP_EOL, FILE_APPEND);
            echo Common::outputResult([
                'code' => 3,
                'msg' => '获取线索查询列表失败，第1页，' . $lifeClueResponseData['extra']['description'],
            ]);
            exit();
        }
        $pageInfo = $lifeClueResponseData['data']['page'];
        if (!$pageInfo['total']) {
            file_put_contents($this->controller->logFilename, date('Y-m-d H:i:s') . "\t获取线索查询总数为空，退出执行" . PHP_EOL, FILE_APPEND);
            echo Common::outputResult([
                'code' => 0,
                'msg' => '获取线索查询总数为空，退出执行',
            ]);
            exit();
        }
        $lifeClueList = $lifeClueResponseData['data']['clue_data'];
        // 有多页数据，循环请求
        for ($currentPage = 2; $currentPage <= $pageInfo['page_total']; $currentPage++) {
            $currentGetData = array(
                'account_id' => $this->controller->accountId,
                'start_time' => $startTime,
                'end_time' => date('Y-m-d H:i:s', time() - 600),
                'page' => $currentPage,
                'page_size' => $pageSize,
            );
            $currentLifeClueResponseRes = $this->curlGet($requestUrl, $currentGetData, $accessToken);
            file_put_contents($this->controller->logFilename, date('Y-m-d H:i:s') . "\t获取线索查询列表，第{$currentPage}页，返回结果：" . $currentLifeClueResponseRes . PHP_EOL, FILE_APPEND);
            $currentLifeClueResponseData = json_decode($currentLifeClueResponseRes, true);
            if ($currentLifeClueResponseData['extra']['error_code']) {
                file_put_contents($this->controller->logFilename, date('Y-m-d H:i:s') . "\t获取线索查询列表失败，第{$currentPage}页，错误码：" . $currentLifeClueResponseData['extra']['error_code'] . '，错误信息：' . $currentLifeClueResponseData['extra']['description'] . PHP_EOL, FILE_APPEND);
                echo Common::outputResult([
                    'code' => 3,
                    'msg' => '获取线索查询列表失败，第' . $currentPage . '页，' . $currentLifeClueResponseData['extra']['description'],
                ]);
                continue;
            }
            $lifeClueList = array_merge($lifeClueList, $currentLifeClueResponseData['data']['clue_data']);
        }

        if (!is_array($lifeClueList) || empty($lifeClueList)) {
            file_put_contents($this->controller->logFilename, date('Y-m-d H:i:s') . "\t获取线索查询列表为空，退出执行" . PHP_EOL, FILE_APPEND);
            echo Common::outputResult([
                'code' => 0,
                'msg' => '获取线索查询列表为空，退出执行',
            ]);
            exit();
        }
        foreach ($lifeClueList as $key => $value) {
            // 每推送一条数据，记录缓存30天，防止重复推送
            $memClueIdKey = 'crm_douyin_clue_id_' . $value['clue_id'];
            if (Yii::$app->memcache->get($memClueIdKey)) {
                $jumpCount++;
                continue;
            }
            // 手机号解密
            if ($value['telephone']) {
                $iv = substr($this->controller->secret, 16);
                $telephone = $this->_decryptWithOpenssl($value['telephone'], $this->controller->secret, $iv);
                $lifeClueList[$key]['telephone'] = $value['telephone'] = $telephone;
            }
            /*$postData = [
                'create_time_detail' => $value['create_time_detail'],
                'name' => $value['name'],
                'advertiser_name' => $value['advertiser_name'],
                'telephone' => $value['telephone'],
                'city_name' => $value['city_name'],
                'address' => $value['address'],
                'remark_dict' => $value['remark_dict'],
                'clue_id' => $value['clue_id'],
                // 'local_account_id' => $value['local_account_id'],
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
                // 新增字段
                'ad_id' => $value['ad_id'],
                'advertiser_id' => $value['advertiser_id'],
                'allocation_status' => $value['allocation_status'],
                'clue_owner_name' => $value['clue_owner_name'],
                'effective_state' => $value['effective_state'],
                'follow_life_account_id' => $value['follow_life_account_id'],
                'follow_life_account_name' => $value['follow_life_account_name'],
                'follow_life_account_type' => $value['follow_life_account_type'],
                'follow_state_name' => $value['follow_state_name'],
                'intention_life_account_name' => $value['intention_life_account_name'],
                'intention_poi_id' => $value['intention_poi_id'],
                'modify_time' => $value['modify_time'],
                'product_id' => $value['product_id'],
                'product_name' => $value['product_name'],
                'province_name' => $value['province_name'],
                'remark' => $value['remark'],
                'req_id' => $value['req_id'],
                'root_life_account_id' => $value['root_life_account_id'],
                'system_tags' => $value['system_tags'],
                'tags' => $value['tags'],
                'tel_addr' => $value['tel_addr'],
            ];*/
            $crmRes = Http::curlPostJson($this->controller->crmPostUrl, json_encode($value));
            $crmData = json_decode($crmRes, true);
            if ($crmData['code'] != 1) { // 推送失败的跳过，下次再推送
                $errorCount++;
                continue;
            }
            Yii::$app->memcache->set($memClueIdKey, 1, 86400 * 7);
            file_put_contents($this->controller->logFilename, date('Y-m-d H:i:s') . "\t线索查询结果推送CRM成功，" . $memClueIdKey . "，" . json_encode($value, JSON_UNESCAPED_UNICODE) . PHP_EOL . "\tCRM返回结果：" . json_encode($crmData, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
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
        file_put_contents($this->controller->logFilename, date('Y-m-d H:i:s') . "\t获取线索查询列表成功，共计{$pageInfo['total']}条，且已推送CRM" . $remark . PHP_EOL, FILE_APPEND);
        echo Common::outputResult([
            'code' => 0,
            'msg' => '获取线索查询列表成功，共计' . $pageInfo['total'] . '条，且已推送CRM' . $remark,
            'data' => [
                'life_clue_list' => $lifeClueList,
            ],
        ]);
        exit();
    }

    /**
     * Send GET request
     * @return bool|string : Response in JSON format
     */
    public
    function curlGet($url = '', $getParamArr = [], $accessToken = '')
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
                "access-token: " . $accessToken,
                "content-type: application/json",
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }

    /**
     * 字节抖音php解密留资的手机号
     * https://partner.open-douyin.com/docs/resource/zh-CN/local-life/develop/OpenAPI/preparation/decrypt
     * 将ClientSecret作为Key， 右侧16位为向量IV
     * @param string $data
     * @param string $key
     * @param string $iv
     * @return string
     */
    private
    function _decryptWithOpenssl($data, $key, $iv)
    {
        return openssl_decrypt(base64_decode($data), "AES-256-CBC", $key, OPENSSL_RAW_DATA, $iv);
    }
}
