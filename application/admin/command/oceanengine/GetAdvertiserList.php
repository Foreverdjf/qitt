<?php
namespace app\admin\command\oceanengine;
/**
 * 获取纵横组织下账户列表，拿到广告主ID
 * 每30分钟执行一次，/usr/bin/curl https://apis.fengniao.com/index.php?r=bytedance/oceanengine/get_advertiser_list
 */
use app\services\KeysUtil;
use think\Cache;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Log;
use Throwable;

class GetAdvertiserList extends Command
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
        $this->setName('oceanengine:GetAdvertiserList')
            ->setDescription('获取纵横组织下账户列表，拿到广告主ID');
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
        if($localAccountIdsInfoJson){
            $localAccountIdsInfo = json_decode($localAccountIdsInfoJson, true);
            // 2个小时更新一次缓存
            if (time() - $localAccountIdsInfo['time'] < 7200) {
                Log::info("从缓存中读取广告主ids成功，" . json_encode($localAccountIdsInfo['local_account_ids'], JSON_UNESCAPED_UNICODE));
                echo outputResult([
                    'code' => 0,
                    'msg' => '从缓存中读取广告主ids成功',
                    'data' => [
                        'local_account_ids' => $localAccountIdsInfo['local_account_ids'],
                    ],
                ]).PHP_EOL;
                return true;
            }
        }
        $accessTokenInfoJson = Cache::get(KeysUtil::oceanengineAccessTokenInfo());
        if (!$accessTokenInfoJson) {
            Log::error("access token不存在，请先授权或者刷新");
            echo outputResult([
                'code' => 1,
                'msg' => 'access token不存在，请先授权或者刷新',
            ]).PHP_EOL;
            return false;
        }
        $accessTokenInfo = json_decode($accessTokenInfoJson, true);
        $accessToken = $accessTokenInfo['access_token'];
        // 获取已授权账户
        $advertiserRequestUrl = 'https://ad.oceanengine.com/open_api/oauth2/advertiser/get/';
        $advertiserParamArr = array(
            'access_token' => $accessToken,
        );
        $advertiserResponseRes = curl_request($advertiserRequestUrl,'GET',$advertiserParamArr);
        Log::info("获取已授权账户，返回结果：" . $advertiserResponseRes);
        $advertiserResponseData = json_decode($advertiserResponseRes, true);
        if ($advertiserResponseData['code']) {
            Log::error('获取已授权账户失败，错误码：'. $advertiserResponseData['code']. '，错误信息：'. $advertiserResponseData['message']);
            echo outputResult([
                'code' => 2,
                'msg' => '获取已授权账户失败，' . $advertiserResponseData['message'],
            ]).PHP_EOL;
            return false;
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
        $advertiserListResponseRes = curlGet($advertiserListRequestUrl, $advertiserListParamArr, $accessToken);
        Log::info("获取纵横组织下账户列表，返回结果：" . $advertiserListResponseRes);
        $advertiserListResponseData = json_decode($advertiserListResponseRes, true);
        if ($advertiserListResponseData['code']) {
            Log::error('获取纵横组织下账户列表失败，错误码：'. $advertiserListResponseData['code']. '，错误信息：'. $advertiserListResponseData['message']);
            echo outputResult([
                'code' => 3,
                'msg' => '获取纵横组织下账户列表失败，' . $advertiserListResponseData['message'],
            ]).PHP_EOL;
            return false;
        }
        $advertiserList = $advertiserListResponseData['data']['list'];
        $advertiserIdArr = array_column($advertiserList, 'advertiser_name', 'advertiser_id');
        $localAccountIdsInfo = [
            'local_account_ids' => $advertiserIdArr,
            'time' => time(),
        ];
        // 先清理历史缓存
        Cache::rm(KeysUtil::oceanengineLocalAccountIds());
        // 把纵横组织下广告主ID写入缓存
        Cache::set(KeysUtil::oceanengineLocalAccountIds(), json_encode($localAccountIdsInfo), 86400);
        Log::info("获取纵横组织下账户列表成功，". json_encode($localAccountIdsInfo, JSON_UNESCAPED_UNICODE));
        echo outputResult([
            'code' => 0,
            'msg' => '获取广告主ids成功',
            'data' => [
                'local_account_ids' => $advertiserIdArr,
            ],
        ]).PHP_EOL;
        echo '总用时'.(microtime(true)-$a_stime).PHP_EOL;
        return 1;
    }
}