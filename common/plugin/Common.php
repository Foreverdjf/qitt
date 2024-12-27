<?php

/**
 * 公共函数库
 */

namespace common\plugin;

use Yii;
use yii\helpers\Json;
use yii\web\Response;

class Common
{
    /**
     * 返回结果通知
     *
     * @param boolean $flag 结果的标识 true成功 false失败
     * @param int $code 错误代码
     * @param string $msg 错误代码说明
     * @param array $data 成功返回的数据
     * @param string $outType 返回格式，默认json
     *
     * @return json|array|object
     */
    public static function outputResult($paramArr)
    {
        $options = array(
            'code' => '0',
            'msg' => '成功',
            'data' => array(),
            'expires' => time(),
            'outType' => 'json',
        );
        $options = array_merge($options, $paramArr);
        extract($options);
        $res = array(
            'code' => (string)$code,
            'msg' => $msg,
            'expires' => $expires,
        );
        if ($code == 0 || isset($data['phone'])) {
            $res['data'] = $data;
        }
        if ($outType == 'json') {
            // $jsonRes = json_encode($res);
            $jsonRes = json_encode($res, JSON_UNESCAPED_UNICODE);
            header('Content-Type:application/json; charset=utf-8');
            header('Content-Length:' . strlen($jsonRes));
            // 不好用
            /*Yii::$app->response->format = Response::FORMAT_JSON;
            Yii::$app->response->charset = 'UTF-8';
            Yii::$app->response->headers->set('Content-Length', strlen($jsonRes));*/
            return $jsonRes;
        } else if ($outType == 'jsonp') {
            // $jsonRes = json_encode($res);
            $jsonRes = json_encode($res, JSON_UNESCAPED_UNICODE);
            return $jsonRes;
        }
        return $res;
    }

    /**
     * @param string $path 必须是服务器绝对路径
     * @param boolean $isFile 是否是创建文件
     * @param string $mode 创建的目录权限
     *
     * @return boolean
     * @todo 递归创建目录|文件
     *
     */
    public static function recurDirs($paramArr)
    {
        $options = array(
            'path' => '',
            'isFile' => false,
            'mode' => 0777,
        );
        $options = array_merge($options, $paramArr);
        extract($options);
        $dirArr = explode('/', $path);
        if (!is_array($dirArr) || empty($dirArr)) {
            return false;
        }
        if ($isFile) {
            array_pop($dirArr);
        }
        $dirStr = '';
        foreach ($dirArr as $dir) {
            $dirStr .= '/' . $dir;
            if (!is_dir($dirStr)) {
                @mkdir($dirStr, $mode, true);
                @chmod($dirStr, $mode);
            }
        }
        if ($isFile) {
            if (!file_exists($path)) {
                @touch($path);
                @chmod($path, $mode);
            }
        }

        return true;
    }
}
