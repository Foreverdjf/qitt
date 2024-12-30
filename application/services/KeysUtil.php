<?php
namespace app\services;

class KeysUtil
{
    /**
     * 存储access token全部信息的缓存key
     * @return string
     */
    public static function memAccessTokenInfo()
    {
        return 'douyin_access_token_info';
    }

    /**
     * 存储access token全部信息的缓存key
     * @return string
     */
    public static function oceanengineAccessTokenInfo()
    {
        return 'oceanengine_access_token_info';
    }

    /**
     * 刷新access token的缓存key
     * @return string
     */
    public static function oceanengineRefreshToken()
    {
        return 'oceanengine_refresh_token';
    }

    /**
     * 纵横组织下广告主ids的缓存key
     * @return string
     */
    public static function oceanengineLocalAccountIds()
    {
        return 'oceanengine_local_account_ids';
    }


}