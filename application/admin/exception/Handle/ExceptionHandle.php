<?php

namespace app\admin\exception\Handle;

use app\admin\library\DingMessage;
use app\admin\library\Helper;
use Exception;
use think\exception\ErrorException;
use think\exception\Handle;
use think\Request;

class ExceptionHandle extends Handle
{
    protected $ignoreReport = [
        '\\think\\exception\\HttpException',
    ];

    /**
     * Report or log an exception.
     *
     * @param \Exception $exception
     * @return void
     */
    public function report(Exception $exception)
    {
        parent::report($exception);
    }
}
