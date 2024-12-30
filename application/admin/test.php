<?php
/**
 * Created by PhpStorm.
 * User: wanghongda
 * Date: 2020-07-22
 * Time: 15:51
 */

return [
    'app_debug'  => true,
    'break_reconnect' => true,
    'redis' =>[
        // 驱动方式
        'type'       => 'redis',
        // 服务器地址
        'host'       => '',
        'port'       => 3369,
        'timeout'    => 0,
        'password'   => '',

    ],
];