<?php

namespace app\index\controller;

use think\Controller;
use think\Response;

class BaseController extends Controller
{
    private $statusOkCode      = 200;  // 成功状态码   200
    private $statusFailCode    = 400;  // 失败状态码   400
    private $statusUnlistedCode= 401;  // 未登录状态码  401

    /**
     * 格式化输出数组到 json
     *
     * @param array $res
     * @return string
     */
    public function fmtJson($res)
    {
        return json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }


    /**
     * Notes: Date: 2020/11/2
     * 成功请求 200
     * @param $message
     * @param array $data
     * @param null $code
     * @return Response
     */
    public function makeResponse($message, $data = [], $code = null)
    {
        $return = [
            'code'        => $code?:$this->statusOkCode,
            'msg'         => $message,
            'server_time' => time(),
            'data'        => $data
        ];

        return response(json_encode($return,512|JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Notes: Date: 2020/11/2
     * 失败请求 400
     * @param string $message
     * @param array $data
     * @return \think\Response
     */
    public function make400Response(string $message = "", array $data= []): Response
    {
        return $this->makeResponse($message,$data,$this->statusFailCode);
    }

    /**
     * Notes: Date: 2020/11/3
     * 未登录状态码
     * @param string $message
     * @return \think\Response
     */
    public function make401Response($message = "未登录")
    {
        return $this->makeResponse($message,[],$this->statusUnlistedCode);
    }
}
