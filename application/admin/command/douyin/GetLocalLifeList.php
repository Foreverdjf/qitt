<?php
namespace app\admin\command\douyin;

use app\services\KeysUtil;
use think\Cache;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Log;
use Throwable;

class GetLocalLifeList extends Command
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
        $this->setName('douyin:GetLocalLifeList')
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
        $accessTokenInfoJson = Cache::get(KeysUtil::memAccessTokenInfo());
        if (!$accessTokenInfoJson) {
            Log::error("access token不存在，请先授权或者刷新" . PHP_EOL);
            echo outputResult([
                'code' => 2,
                'msg' => 'access token不存在，请先授权或者刷新',
            ]).PHP_EOL;
            return 0;
        }
        $accessTokenInfo = json_decode($accessTokenInfoJson, true);
        $accessToken = $accessTokenInfo['access_token'];
        // 通过第一页数据获取总页数
        $requestUrl = 'https://open.douyin.com/goodlife/v1/open_api/crm/clue/query/';
        $pageSize = 100;
        $startTime = date('Y-m-d H:i:s', time() - 3600);
        $getData = array(
            'account_id' => $this->accountId,
            'start_time' => $startTime,
            'end_time' => date('Y-m-d H:i:s', time() - 600),
            'page' => 1,
            'page_size' => $pageSize,
        );
        $lifeClueResponseRes = curlGet($requestUrl,$getData,$accessToken);
        Log::info("获取线索查询列表，第1页，返回结果：" . $lifeClueResponseRes . PHP_EOL);
        $lifeClueResponseData = json_decode($lifeClueResponseRes, true);
        if ($lifeClueResponseData['extra']['error_code']) {
            Log::error("获取线索查询列表失败，第1页，错误码：" . $lifeClueResponseData['extra']['error_code'] . '，错误信息：' . $lifeClueResponseData['extra']['description'] . PHP_EOL);
            echo outputResult([
                'code' => 3,
                'msg' => '获取线索查询列表失败，第1页，' . $lifeClueResponseData['extra']['description'],
            ]).PHP_EOL;
            return false;
        }
        $pageInfo = $lifeClueResponseData['data']['page'];
        if (!$pageInfo['total']) {
            Log::info("获取线索查询总数为空，退出执行" . PHP_EOL);
            echo outputResult([
                'code' => 0,
                'msg' => '获取线索查询总数为空，退出执行',
            ]).PHP_EOL;
            return false;
        }
        $lifeClueList = $lifeClueResponseData['data']['clue_data'];
        // 有多页数据，循环请求
        for ($currentPage = 2; $currentPage <= $pageInfo['page_total']; $currentPage++) {
            $currentGetData = array(
                'account_id' => $this->accountId,
                'start_time' => $startTime,
                'end_time' => date('Y-m-d H:i:s', time() - 600),
                'page' => $currentPage,
                'page_size' => $pageSize,
            );
            $currentLifeClueResponseRes = curlGet($requestUrl, $currentGetData, $accessToken);
            Log::info("获取线索查询列表，第{$currentPage}页，返回结果：" . $currentLifeClueResponseRes . PHP_EOL);
            $currentLifeClueResponseData = json_decode($currentLifeClueResponseRes, true);
            if ($currentLifeClueResponseData['extra']['error_code']) {
                Log::info("获取线索查询列表失败，第{$currentPage}页，错误码：" . $currentLifeClueResponseData['extra']['error_code'] . '，错误信息：' . $currentLifeClueResponseData['extra']['description']);
                echo outputResult([
                    'code' => 3,
                    'msg' => '获取线索查询列表失败，第' . $currentPage . '页，' . $currentLifeClueResponseData['extra']['description'],
                ]).PHP_EOL;
                continue;
            }
            $lifeClueList = array_merge($lifeClueList, $currentLifeClueResponseData['data']['clue_data']);
        }
        if (!is_array($lifeClueList) || empty($lifeClueList)) {
            Log::error("获取线索查询列表为空，退出执行");
            echo outputResult([
                'code' => 0,
                'msg' => '获取线索查询列表为空，退出执行',
            ]).PHP_EOL;
            return false;
        }
        $jumpCount = $errorCount = 0;
        foreach ($lifeClueList as $key => $value) {
            // 每推送一条数据，记录缓存30天，防止重复推送
            $memClueIdKey = 'crm_douyin_clue_id_' . $value['clue_id'];
            if (Cache::get($memClueIdKey)) {
                $jumpCount++;
                continue;
            }
            // 手机号解密
            if ($value['telephone']) {
                $iv = substr($this->secret, 16);
                $telephone = $this->_decryptWithOpenssl($value['telephone'], $this->secret, $iv);
                $lifeClueList[$key]['telephone'] = $value['telephone'] = $telephone;
            }
            $crmRes = curlPostJson($this->crmPostUrl,json_encode($value, JSON_UNESCAPED_UNICODE));
            $crmData = json_decode($crmRes, true);
            if ($crmData['code'] != 1) { // 推送失败的跳过，下次再推送
                $errorCount++;
                continue;
            }
            Cache::set($memClueIdKey, 1, 86400 * 7);
            Log::info("线索查询结果推送CRM成功，" . $memClueIdKey . "，" . json_encode($value, JSON_UNESCAPED_UNICODE) . PHP_EOL . "CRM返回结果：" . json_encode($crmData, JSON_UNESCAPED_UNICODE) );
            $milliseconds = 300 * 1000; // 休眠300毫秒
            usleep($milliseconds);
        }
        $remark = '';
        if ($jumpCount) {
            $remark = '，有跳过重复的数据' . $jumpCount . '条';
        } else if ($errorCount) {
            $remark .= '，有推送失败的数据' . $errorCount . '条';
        }
        Log::info("获取线索查询列表成功，共计{$pageInfo['total']}条，且已推送CRM" . $remark);
        echo outputResult([
            'code' => 0,
            'msg' => '获取线索查询列表成功，共计' . $pageInfo['total'] . '条，且已推送CRM' . $remark,
            'data' => [
                'life_clue_list' => $lifeClueList,
            ],
        ]).PHP_EOL;
        echo '总用时'.(microtime(true)-$a_stime).PHP_EOL;
        return 1;
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
    private function _decryptWithOpenssl($data, $key, $iv)
    {
        return openssl_decrypt(base64_decode($data), "AES-256-CBC", $key, OPENSSL_RAW_DATA, $iv);
    }
}