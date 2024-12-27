<?php

/**
 * 巨量引擎 开放平台接口
 * https://bytedance.larkoffice.com/docx/Vafvd0nXOoSj43xmkRfcWf2GnJk
 * https://open.oceanengine.com/labels/37/docs/1696710497745920
 */

namespace frontend\controllers\bytedance;

use Yii;
use yii\helpers\ArrayHelper;
use yii\web\Controller;
use yii\web\Response;
use yii\helpers\Json;
use common\plugin\Http;
use common\plugin\Common;

class OceanengineController extends Controller
{

    /*
     * 小号
     * 18601021125
     * 1800913775686684
     * b951be1a012392c0af2241fb8b6264f324679ba5
     *
     * 大号
     * 13693135537
     * 1800896954934284
     * c382b654f36ca0a30eb8fcb13b8fc80177ce1e11
     */

    public $logPath = '/tmp/bytedance/oceanengine'; // 日志目录OceanengineController.php
    public $logFilename = ''; // 日志文件名
    public $appid = '1800896954934284'; // 开发者应用ID
    public $secret = 'c382b654f36ca0a30eb8fcb13b8fc80177ce1e11'; // 应用密钥
    public $state = 'your_custom_params'; // 自定义参数
    public $memAccessTokenInfo = 'oceanengine_access_token_info'; // 存储access token全部信息的缓存key
    public $memRefreshToken = 'oceanengine_refresh_token'; // 刷新access token的缓存key
    public $memLocalAccountIds = 'oceanengine_local_account_ids'; // 纵横组织下广告主ids的缓存key

    public $crmPostUrl = 'https://saas.qikebao.com/api/clue/de81a2b555d5d1c8fa6d7474107147ae'; // 把本地推的数据通知CRM

    public function actions()
    {
        return [
            'callback' => 'frontend\actions\bytedance\oceanengine\Callback', // 授权后回调地址
            'access_token' => 'frontend\actions\bytedance\oceanengine\AccessToken', // 保持access token活跃有效，自动运行，每30分钟执行一次
            'get_advertiser_list' => 'frontend\actions\bytedance\oceanengine\GetAdvertiserList', // 获取纵横组织下账户列表，定时任务
            'get_life_clue_list' => 'frontend\actions\bytedance\oceanengine\GetLifeClueList', // 获取本地推线索列表，定时任务
            'delete_log' => 'frontend\actions\bytedance\oceanengine\DeleteLog', // 清理日志的脚本
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ]
        ];
    }

    public function beforeAction($action)
    {
        $request = Yii::$app->getRequest();
        $getParams = $request->isGet ? $request->get() : $request->post();

        // 不需要记录日志的方法
        $notRecordLogAction = [
            'test',
        ];
        if (in_array($this->action->id, $notRecordLogAction)) {
            return true;
        }

        $this->enableCsrfValidation = false;
        if (!parent::beforeAction($action)) {
            return false;
        }

        // 创建目录
        Common::recurDirs([
            'path' => $this->logPath,
        ]);
        $this->logFilename = $this->logPath . '/' . $this->action->id . '_' . date('Ymd') . '.log';
        if (!file_exists($this->logFilename)) {
            @touch($this->logFilename);
            @chmod($this->logFilename, 0777);

        }
        // 接口收到请求记录日志
        file_put_contents($this->logFilename, "------------------------" . PHP_EOL . date('Y-m-d H:i:s') . "\t开始记录日志，请求参数：" . json_encode($getParams, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
        return true;
    }

}
