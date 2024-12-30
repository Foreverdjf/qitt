<?php
namespace app\admin\command\oceanengine;
/**
 * 获取本地推线索列表
 * 每3分钟执行一次，/usr/bin/curl https://apis.fengniao.com/index.php?r=bytedance/oceanengine/get_life_clue_list
 */
use app\services\KeysUtil;
use think\Cache;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Log;
use Throwable;

class GetLifeClueList extends Command
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
        $this->setName('oceanengine:GetLifeClueList')
            ->setDescription('获取本地推线索列表');
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
        $localAccountIdsInfoJson = Cache::get(KeysUtil::oceanengineLocalAccountIds());
        if (!$localAccountIdsInfoJson) {
            Log::error("纵横组织下广告主ids不存在，请先获取");
            echo outputResult([
                'code' => 1,
               'msg' => '纵横组织下广告主ids不存在，请先获取',
            ]).PHP_EOL;
            return false;
        }
        $localAccountIdsInfo = json_decode($localAccountIdsInfoJson, true);
        $localAccountIds = array_keys($localAccountIdsInfo['local_account_ids']);
        if (!is_array($localAccountIds) || empty($localAccountIds)) {
            Log::error("纵横组织下广告主ids不存在，请先获取");
            echo outputResult([
                    'code' => 1,
                    'msg' => '纵横组织下广告主ids不存在，请先获取',
                ]).PHP_EOL;
            return false;
        }
        // 从缓存中读取access token
        $accessTokenInfoJson = Cache::get(KeysUtil::oceanengineAccessTokenInfo());
        if (!$accessTokenInfoJson) {
            Log::error("access token不存在，请先授权或者刷新");
            echo outputResult([
                'code' => 2,
                'msg' => 'access token不存在，请先授权或者刷新',
            ]);
            return false;
        }
        $accessTokenInfo = json_decode($accessTokenInfoJson, true);
        $accessToken = $accessTokenInfo['access_token'];
        // 通过第一页数据获取总页数
        $requestUrl = 'https://api.oceanengine.com/open_api/2/tools/clue/life/get/';
        $pageSize = 100;
        $startTime = date('Y-m-d H:i:s', time() - 3600);
        $postData = array(
            'local_account_ids' => $localAccountIds,
            'start_time' => $startTime,
            'end_time' => date('Y-m-d H:i:s'),
            'page' => 1,
            'page_size' => $pageSize,
        );
        $lifeClueResponseRes = curlPost($requestUrl,$postData, $accessToken);
        Log::info("获取本地推线索列表，第1页，返回结果：". $lifeClueResponseRes);
        $lifeClueResponseData = json_decode($lifeClueResponseRes, true);
        if ($lifeClueResponseData['code']) {
            Log::error("获取本地推线索列表失败，第1页，错误码：". $lifeClueResponseData['code']. '，错误信息：'. $lifeClueResponseData['message']);
            echo outputResult([
                'code' => 3,
                'msg' => '获取本地推线索列表失败，第1页，' . $lifeClueResponseData['message'],
            ]).PHP_EOL;
            return false;
        }
        $pageInfo = $lifeClueResponseData['data']['page_info'];
        if (!$pageInfo['total']) {
            Log::error("获取本地推线索总数为空，退出执行");
            echo outputResult([
                'code' => 0,
                'msg' => '获取本地推线索总数为空，退出执行',
            ]).PHP_EOL;
            return false;
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
            $currentLifeClueResponseRes = curlPost($requestUrl, $currentPostData, $accessToken);
            Log::info("获取本地推线索列表，第{$currentPage}页，返回结果：". $currentLifeClueResponseRes);
            $currentLifeClueResponseData = json_decode($currentLifeClueResponseRes, true);
            if ($currentLifeClueResponseData['code']) {
                Log::error("获取本地推线索列表失败，第{$currentPage}页，错误码：". $currentLifeClueResponseData['code']. '，错误信息：'. $currentLifeClueResponseData['message']);
                echo outputResult([
                    'code' => 3,
                    'msg' => '获取本地推线索列表失败，第' . $currentPage . '页，' . $currentLifeClueResponseData['message'],
                ]).PHP_EOL;
                continue;
            }
            $lifeClueList = array_merge($lifeClueList, $currentLifeClueResponseData['data']['list']);
        }

        if (!is_array($lifeClueList) || empty($lifeClueList)) {
            Log::error("获取本地推线索列表为空，退出执行");
            echo outputResult([
                'code' => 0,
                'msg' => '获取本地推线索列表为空，退出执行',
            ]).PHP_EOL;
            return false;
        }
        $jumpCount = $errorCount = 0;
        foreach ($lifeClueList as $key => $value) {
            // 每推送一条数据，记录缓存30天，防止重复推送
            $memClueIdKey = 'crm_clue_id_' . $value['clue_id'];
            if (Cache::get($memClueIdKey)) {
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
            $crmRes = curlPostJson($this->crmPostUrl, json_encode($postData));
            $crmData = json_decode($crmRes, true);
            if ($crmData['code'] != 1) { // 推送失败的跳过，下次再推送
                $errorCount++;
                continue;
            }
            Cache::set($memClueIdKey, 1, 86400 * 7);
            // 记录推送日志
            Log::info("本地推线索推送CRM成功，". $memClueIdKey. "，". json_encode($postData, JSON_UNESCAPED_UNICODE). PHP_EOL. "CRM返回结果：". json_encode($crmData, JSON_UNESCAPED_UNICODE) );
            $milliseconds = 300 * 1000; // 休眠300毫秒
            usleep($milliseconds);
        }
        $remark = '';
        if ($jumpCount) {
            $remark = '，有跳过重复的数据' . $jumpCount . '条';
        } else if ($errorCount) {
            $remark .= '，有推送失败的数据' . $errorCount . '条';
        }
        Log::info("获取本地推线索列表成功，共计{$pageInfo['total']}条，且已推送CRM". $remark);
        echo outputResult([
            'code' => 0,
            'msg' => '获取本地推线索列表成功，共计' . $pageInfo['total'] . '条，且已推送CRM' . $remark,
            'data' => [
                'life_clue_list' => $lifeClueList,
            ],
        ]).PHP_EOL;
        echo '总用时'.(microtime(true)-$a_stime).PHP_EOL;
        return 1;
    }
}