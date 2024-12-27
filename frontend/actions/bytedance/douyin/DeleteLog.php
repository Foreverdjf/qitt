<?php

/**
 * 清理日志的脚本
 * 每天执行一次，/usr/bin/curl https://apis.fengniao.com/index.php?r=bytedance/douyin/delete_log&code=dex1ilnlj63old84
 */

namespace frontend\actions\bytedance\douyin;

use yii;
use yii\base\Action;

class DeleteLog extends Action
{

    public function run()
    {

        $request = Yii::$app->request;
        $getParams = Yii::$app->request->get();
        $code = $request->get('code');    // 标识符

        if ($code != 'dex1ilnlj63old84') {
            file_put_contents($this->controller->logFilename, date('Y-m-d H:i:s') . "\t无权限" . PHP_EOL, FILE_APPEND);
            exit();
        }
        $fileList = [
            'access_token',
            'get_local_life_list',
            'delete_log',
        ];
        foreach ($fileList as $file) {
            $filename = $file . '_' . date('Ymd', strtotime('-7 day')) . '.log';
            $filePath = $this->controller->logPath . '/' . $filename;
            if (file_exists($filePath)) {
                @unlink($filePath);
                file_put_contents($this->controller->logFilename, date('Y-m-d H:i:s') . "\t删除文件：{$filename}成功" . PHP_EOL, FILE_APPEND);
            }
        }
        exit();
    }
}
