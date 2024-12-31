<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com>
// +----------------------------------------------------------------------

// 应用公共文件
//

if (!function_exists('allConf')) {
    /**
     * 获取配置，ENV 配置优先级高于 config 配置
     *
     * @param [type] $confName
     * @param [type] $defaultValue
     * @return mixed
     */
    function allConf($confName = null, $defaultValue = null)
    {
        $result = null;
        if ($result = env($confName)) {
            return $result;
        }

        // 如果是测试环境，并且存在以 test_ 开头的同名配置，优先加载 test_ 开头的同名配置
        $confInfo = explode('.', $confName);
        $confInfo[count($confInfo) - 1] = 'test_' . $confInfo[count($confInfo) - 1];
        $testConfName = implode('.', $confInfo);
        if (env('is_test') && $result = config($testConfName)) {
            return $result;
        } else {
            if ($result = config($confName)) {
                return $result;
            }
        }

        return $defaultValue;
    }
}

if (!function_exists('dd')) {
    /**
     * 打印并结束进程
     * @param mixed $param
     * @return string dump 结果
     */
    function dd($param)
    {
        var_dump($param);
        exit(0);
    }
}

if (!function_exists('curl_request')) {
    /**
     * CURL 封装
     *
     * @param [type] $api
     * @param string $method
     * @param array $params
     * @param array $headers
     * @param boolean $json_decode
     * @return mixed
     */
    function curl_request($api, $method = 'GET', $params = array(), $headers = [], $json_decode = true)
    {
        // 记录 curl 请求日志
        $curlInfo = json_encode([
            'api' => $api,
            'method' => $method,
            'params' => $params,
        ], JSON_UNESCAPED_UNICODE);
        think\Log::write('[curl]' . $curlInfo);

        $curl = curl_init();

        switch (strtoupper($method)) {
            case 'GET':
                if (!empty($params)) {
                    $api .= (strpos($api, '?') ? '&' : '?') . http_build_query($params);
                }
                curl_setopt($curl, CURLOPT_HTTPGET, true);
                break;
            case 'POST':
                curl_setopt($curl, CURLOPT_POST, true);
                if (is_array($params)) {
                    $params = http_build_query($params);
                }
                curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
                break;
            case 'PUT':
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
                break;
            case 'DELETE':
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
                curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
                break;
        }

        if (preg_match('/^https/', $api)) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        }

        curl_setopt($curl, CURLOPT_URL, $api);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_MAXREDIRS, 10);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($curl, CURLOPT_TIMEOUT, 120);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($curl);
        curl_close($curl);
        if ($json_decode) {
            return json_decode($response, true);
        }
        return $response;
    }
}

if(!function_exists('curlGet')){
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
}

if (!function_exists('getProcessIDList')) {
    /**
     * 获取指定关键字的进程 ID 列表
     *
     * @param [type] $keywords
     * @return array
     */
    function getProcessIDList($keywords)
    {
        exec('ps -ef | grep ' . $keywords . ' | grep -v grep | cut -c 9-15', $list);
        return $list;
    }
}

if (!function_exists('getServerLocalIP')) {
    /**
     * 获取本机以太网卡 0 的 IP，无则返回空字符串
     *
     * @return string
     */
    function getServerLocalIP()
    {
        exec('ifconfig eth0 | grep "inet "', $res);
        if (!$res) {
            return '';
        }
        $ipInfo = explode(' ', trim($res[0]));
        if (isset($ipInfo[1]) && preg_match('/^(\d{0,3}.){4}$/', $ipInfo[1])) {
            return $ipInfo[1];
        } else {
            return '';
        }
    }
}

if (!function_exists('xinput')) {
    /**
     * 增强 input 函数，用于校验参数是否合法
     *
     * @param string $key
     * @param array $filter [bool, 正则]
     * @return mixed
     */
    function xinput($key, $filter = [])
    {
        $res = input($key);

        // 校验必传参数是否为空
        if ($filter[0] && $res === null) {
            fmtRes(400, '参数：' . explode('.', $key)[1] . ' 不为空');
        }

        // 不为空时，校验参数是否符合正则
        if ($res && isset($filter[1]) && !preg_match($filter[1], $res)) {
            fmtRes(400, '参数：' . explode('.', $key)[1] . ' 不符合参数格式，参数格式为：' . $filter[1]);
        }
        return $res;
    }
}

if (!function_exists('fmtRes')) {
    /**
     * 格式化结果输出 json
     *
     * @param integer $code
     * @param string $msg
     * @param array $data
     * @return string
     */
    function fmtRes($code = 200, $msg = '', $data = [])
    {
        header('Content-Type: application/json');
        $jsonData = array(
            'code' => $code,
            'msg' => $msg,
            'data' => $data,
        );
        echo json_encode($jsonData, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if(!function_exists('outputResult')) {
    /**
     * 返回结果通知
     *
     * @param $paramArr
     * @return array|false|string
     */
    function outputResult($paramArr)
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
            $jsonRes = json_encode($res, JSON_UNESCAPED_UNICODE);
            return $jsonRes;
        } else if ($outType == 'jsonp') {
            $jsonRes = json_encode($res, JSON_UNESCAPED_UNICODE);
            return $jsonRes;
        }
        return $res;
    }
}

if (!function_exists('env')) {
    /**
     * 获取环境变量值
     * @access public
     * @param string $name 环境变量名（支持二级 .号分割）
     * @param string $default 默认值
     * @return mixed
     */
    function env($name = null, $default = null)
    {
        return think\Env::get($name, $default);
    }
}

if (!function_exists('curlPostJson')) {
    function curlPostJson($url, $postData)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);   //没有这个会自动输出，不用print_r()也会在后面多个1
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($postData)
        ));
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }
}

if (!function_exists('curlPost')) {
    /**
     * Send POST request
     * @param string $url
     * @param array $postParamArr
     * @param string $accessToken
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
