<?php

/**
 * 抖音开放平台
 * 抖音来客-客资中心线索接口
 * https://bytedance.larkoffice.com/docx/EGuddOBPgoqgkKxylbgcK56Nnnd
 */

namespace frontend\controllers\bytedance;

use Yii;
use yii\web\Controller;
use common\plugin\Common;

class DouyinController extends Controller
{


    public $logPath = '/tmp/bytedance/douyin'; // 日志目录OceanengineController.php
    public $logFilename = ''; // 日志文件名
    public $appid = 'awk3j12mq8rkyh1u'; // 开发者应用ID
    public $secret = '272f08856efc2f73dcf55383f47afff5'; // 应用密钥
    public $accountId = '7343082414312376371'; // 来客品牌账户
    public $memAccessTokenInfo = 'douyin_access_token_info'; // 存储access token全部信息的缓存key
    public $crmPostUrl = 'https://saas.qikebao.com/api/clue/de81a2b555d5d1c8fa6d7474107147ae'; // 把本地推的数据通知CRM

    public function actions()
    {
        return [
            'access_token' => 'frontend\actions\bytedance\douyin\AccessToken', // 保持access token活跃有效，自动运行，每5分钟执行一次
            'get_local_life_list' => 'frontend\actions\bytedance\douyin\GetLocalLifeList', // 线索查询，定时任务
            'delete_log' => 'frontend\actions\bytedance\douyin\DeleteLog', // 清理日志的脚本
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
