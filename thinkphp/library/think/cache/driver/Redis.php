<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2017 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace think\cache\driver;

use think\cache\Driver;

/**
 * Redis缓存驱动，适合单机部署、有前端代理实现高可用的场景，性能最好
 * 有需要在业务层实现读写分离、或者使用RedisCluster的需求，请使用Redisd驱动
 *
 * 要求安装phpredis扩展：https://github.com/nicolasff/phpredis
 * @author    尘缘 <130775@qq.com>
 */
class Redis extends Driver
{
    protected $options = [
        'host'       => '127.0.0.1',
        'port'       => 6379,
        'password'   => '',
        'select'     => 0,
        'timeout'    => 0,
        'expire'     => 0,
        'persistent' => false,
        'prefix'     => '',
    ];

    /**
     * 构造函数
     * @param array $options 缓存参数
     * @access public
     */
    public function __construct($options = [])
    {
        if (!extension_loaded('redis')) {
            throw new \BadFunctionCallException('not support: redis');
        }
        if (!empty($options)) {
            $this->options = array_merge($this->options, $options);
        }
        $func          = $this->options['persistent'] ? 'pconnect' : 'connect';
        $this->handler = new \Redis;
        $this->handler->$func($this->options['host'], $this->options['port'], $this->options['timeout']);

        if ('' != $this->options['password']) {
            $this->handler->auth($this->options['password']);
        }

        if (0 != $this->options['select']) {
            $this->handler->select($this->options['select']);
        }
    }

    /**
     * 判断缓存
     * @access public
     * @param string $name 缓存变量名
     * @return bool
     */
    public function has($name)
    {
        return $this->handler->get($this->getCacheKey($name)) ? true : false;
    }

    /**
     * 读取缓存
     * @access public
     * @param string $name 缓存变量名
     * @param mixed  $default 默认值
     * @return mixed
     */
    public function get($name, $default = false)
    {
        $value = $this->handler->get($this->getCacheKey($name));
        if (is_null($value) || false === $value) {
            return $default;
        }
        try {
            $result = 0 === strpos($value, 'think_serialize:') ? unserialize(substr($value, 16)) : $value;
        } catch (\Exception $e) {
            $result = $default;
        }

        return $result;
    }

    /**
     * 写入缓存
     * @access public
     * @param string            $name 缓存变量名
     * @param mixed             $value  存储数据
     * @param integer|\DateTime $expire  有效时间（秒）
     * @return boolean
     */
    public function set($name, $value, $expire = null)
    {
        if (is_null($expire)) {
            $expire = $this->options['expire'];
        }
        if ($expire instanceof \DateTime) {
            $expire = $expire->getTimestamp() - time();
        }
        if ($this->tag && !$this->has($name)) {
            $first = true;
        }
        $key   = $this->getCacheKey($name);
        $value = is_scalar($value) ? $value : 'think_serialize:' . serialize($value);
        if ($expire) {
            $result = $this->handler->setex($key, $expire, $value);
        } else {
            $result = $this->handler->set($key, $value);
        }
        isset($first) && $this->setTagItem($key);
        return $result;
    }

    /**
     * 自增缓存（针对数值缓存）
     * @access public
     * @param  string    $name 缓存变量名
     * @param  int       $step 步长
     * @return false|int
     */
    public function inc($name, $step = 1)
    {
        $key = $this->getCacheKey($name);

        return $this->handler->incrby($key, $step);
    }

    /**
     * 自减缓存（针对数值缓存）
     * @access public
     * @param  string    $name 缓存变量名
     * @param  int       $step 步长
     * @return false|int
     */
    public function dec($name, $step = 1)
    {
        $key = $this->getCacheKey($name);

        return $this->handler->decrby($key, $step);
    }

    /**
     * 删除缓存
     * @access public
     * @param string $name 缓存变量名
     * @return boolean
     */
    public function rm($name)
    {
        return $this->handler->delete($this->getCacheKey($name));
    }

    /**
     * 清除缓存
     * @access public
     * @param string $tag 标签名
     * @return boolean
     */
    public function clear($tag = null)
    {
        if ($tag) {
            // 指定标签清除
            $keys = $this->getTagItem($tag);
            foreach ($keys as $key) {
                $this->handler->delete($key);
            }
            $this->rm('tag_' . md5($tag));
            return true;
        }
        return true;//$this->handler->flushDB();
    }

    /**
     * 集合添加数据
     * @param $name
     * @param $value
     * @return int
     */
    public function sadd($name,$value)
    {
        return $this->handler->sAdd($this->options['prefix'].$name, $value);
    }

    public function scard($name){
        return $this->handler->SCARD($this->options['prefix'].$name);
    }
    /**
     * 集合添加数据
     * @param $name
     * @param $value
     * @return int
     */
    public function rmsmembers($name)
    {
        return $this->handler->delete($this->options['prefix'].$name);
    }

    /**
     * 获取集合
     * @param $name
     * @return array
     */
    public function smembers($name)
    {
        return $this->handler->sMembers($this->options['prefix'].$name);
    }

    /**
     * 判断成员元素是否是集合的成员
     * @param $name
     * @param $value
     * @return boolean
     */
    public function sismember($name,$value)
    {
        return $this->handler->sIsMember($this->options['prefix'].$name,$value);
    }

    /**
     * 移除集合中一个
     * @param $name
     * @param $value
     * @return int
     */
    public function srem($name,$value)
    {
        return $this->handler->sRem($this->options['prefix'].$name,$value);
    }

    /**
     * 有序集合添加
     */
    public function zadd($set_key, $score, $name){
        return $this->handler->zAdd($this->options['prefix'].$set_key, $score, $name);
    }

    /**
     * @param $set_key
     * @param $start
     * @param $end
     * @return array
     * 按照score 从大到小 排序
     */
    public function zrevrange($set_key, $start, $end,$withscore =false){
        return $this->handler->zRevRange($this->options['prefix'].$set_key, $start, $end,$withscore);
    }
    public function zrange($set_key, $start, $end,$withscore =false){
        return $this->handler->zRange($this->options['prefix'].$set_key, $start, $end,$withscore);
    }

    public function zrevrank($set_key, $member){
        return $this->handler->zRevRank($this->options['prefix'].$set_key,$member);
    }


    public function decreBy($name,$step=1){
        return $this->handler->decrBy($this->options['prefix'].$name,$step);
    }

    public function zincreby($key,$member,$step=1){
        $rn = $this->handler->zIncrBy($this->options['prefix'].$key,$step,$member);
        return $rn;
    }

    public function zscore($key,$member){
        return $this->handler->zScore($this->options['prefix'].$key,$member);
    }

    public function zrem($key, $member){
        return $this->handler->zRem($this->options['prefix'].$key,$member);
    }

    public function rpush($key,$value)
    {
        $value = is_scalar($value) ? $value : 'think_serialize:' . serialize($value);
        return $this->handler->rPush($this->options['prefix'] . $key, $value);
    }

    public function lpop($key)
    {
        $value = $this->handler->lpop($this->options['prefix'] . $key);
        try {
            $result = 0 === strpos($value, 'think_serialize:') ? unserialize(substr($value, 16)) : $value;
        } catch (\Exception $e) {
            $result = false;
        }

        return $result;
    }

    public function hget($key,$hashKey){
        return $this->handler->hGet($this->options['prefix'].$key,$hashKey);
    }

    public function hset($key,$hashKey,$value){
        return $this->handler->hSet($this->options['prefix'].$key,$hashKey,$value);
    }

    public function hsetnx($key,$hashKey,$value)
    {
        return $this->handler->hSetNx($this->options['prefix'].$key,$hashKey,$value);
    }

    public function hgetall($key){
        return $this->handler->hGetAll($this->options['prefix'].$key);
    }

    /**
     * 获取Redis句柄
     * @return \Redis
     */
    public function getHandler()
    {
        return $this->handler;
    }

    /**
     * 获取实际的缓存标识
     * @access public
     * @param string $name 缓存名
     * @return string
     */
    public function getCacheKey($name)
    {
        return parent::getCacheKey($name);
    }

    public function expire($key,$tll)
    {
        return $this->handler->expire($this->options['prefix'] . $key, $tll);
    }
}
