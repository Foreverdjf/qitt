<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
// [ 应用入口文件 ]

// 定义应用目录
define('APP_PATH', __DIR__ . '/../application/');

$SERVER_ADDR = isset($_SERVER['SERVER_ADDR'])?$_SERVER['SERVER_ADDR']:'';
$argvTest = isset($argv[2])?$argv[2]:'';

if($SERVER_ADDR == '127.0.0.1' || $argvTest == 'test'){
    if(
        isset($_SERVER['HTTP_PERSONAL_TEST_CONFIG'])
        &&
        is_file(APP_PATH . 'admin/' . $_SERVER['HTTP_PERSONAL_TEST_CONFIG'] . '_test.php')
    ){
        define('APP_STATUS', $_SERVER['HTTP_PERSONAL_TEST_CONFIG'] . '_test');
        header('config_file: '. $_SERVER['HTTP_PERSONAL_TEST_CONFIG'] .'_test');
    } else {
        define('APP_STATUS', 'test');
        header('config_file: test');
    }
}

// 加载框架引导文件
require __DIR__ . '/../thinkphp/start.php';

