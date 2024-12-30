<?php
namespace app\admin\command;

use app\admin\model\H5Admin\AdminLog;
use app\admin\model\H5Admin\AdminSearchLog;
use app\admin\model\H5Admin\AdminSearchLogModel;
use app\admin\service\DingDing\Robot;
use services\service\KeysUtil;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use Throwable;

class AdminLogMove extends Command
{
    protected $redis;

    public function init()
    {
        //设置所需的模型和redis
    }

    protected function configure()
    {
        $this->init();
        $this->setName('AdminLogMove')
            ->addArgument('type', Argument::OPTIONAL, '', 0)
            ->setDescription('admin日志log迁移');
    }

    protected function execute(Input $input, Output $output)
    {
        var_dump(KeysUtil::memAccessTokenInfo());die;
        // 实例化重点项目报警机器人
//        $robot = new Robot('admin日志log迁移', '董建丰', '18031386867');
        $type = $input->getArgument('type');
        try {
            $this->main($type);
        } catch (Throwable $e) {
            print_r($e->getFile().'---'.$e->getMessage().'---'.$e->getLine());die;
//            $robot->setMsg($e->getMessage().'---'.$e->getLine())->send();
        }
    }

    /**
     * @param $type 0 默认清洗热表数据 1 迁移旧表数据
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws Exception
     * @throws ModelNotFoundException
     */
    private function main($type)
    {
        $a_stime=microtime(true);
        if($type){
            $this->moveOld();
        }else{
            $this->moveNew();
        }
        echo '总用时'.(microtime(true)-$a_stime).PHP_EOL;
        return true;
    }

    /**
     * @return void
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    private function moveOld()
    {
        $adminSearchLog = new AdminSearchLog();
        $adminLog = new AdminLog();
        //获取原始表最大ID
        $o_maxId = 30953087;//$adminLog->fetchSql(false)->value('max(id) as max');
        //获取新表最大ID
//        $n_maxId = self::getMaxOldLogId();
//        if(empty($n_maxId)){
//            //半年前ID
//            $n_maxId = 30953087;
//        }
        $n_maxId = 0;
        $now = time();
        $limit = 1000;
        $a = 0;
        while($n_maxId < $o_maxId) {
            $stime = microtime(true);
            $min_id = $n_maxId + 1;
            $max_id = bcadd($min_id,$limit);
            $data = $adminLog
                ->fetchSql(false)
                ->where([
                    'id' => ['between',[$min_id,$max_id]]
                ])
                ->select()
                ->toArray();
            //取完数据后重置起点
            $n_maxId = $max_id;
            $a++;
            $insert = [];
            if(!empty($data)){
                foreach ($data as $item){
                    if($item['user_name'] == 'Unknown'){
                        continue;
                    }
                    $year = date('Y',$item['createtime']);
                    $is_hot = false;
                    if(bcsub($now,$item['createtime']) < SECONDS_HALF_YEAR){
                        $is_hot = true;
                    }
                    $left = $item;
                    $left['user_name'] = $item['username'];
                    $left['create_time'] = $item['createtime'];
                    $left['app_id'] = $item['appid'];
                    unset($left['id'],$left['username'],$left['createtime'],$left['appid']);
                    $contentIdArr = $contentTitleArr = [];
                    //解析content内容
                    $content = json_decode($item['content'],true);
                    if(!empty($content)){
                        if(!is_array($content)){
                            $content = (array)$content;
                        }
                        foreach ($content as $k => $v){
                            //如果key名包含id字样 || 或name字样
                            if (stripos($k, 'id') !== false) {
                                if(!empty($v) && is_numeric($v)){
                                    $contentIdArr[] = $v;
                                }
                            }
                            //title 提取
                            if (stripos($k, 'name') !== false || strpos($k, 'title') !== false || strpos($k, 'word') !== false || strpos($k, 'key') !== false) {
                                if(stripos($k, 'config') !== false || $k == 'controllername'){
                                    continue;
                                }
                                if(!empty($v) && is_numeric($v)){
                                    $contentTitleArr[] = $v;
                                }
                            }
                        }
                    }
                    if(!empty($contentIdArr) || !empty($contentTitleArr)){
                        //过滤重复数据
                        $contentIdArr = array_filter(array_unique($contentIdArr));
                        $contentTitleArr = array_filter(array_unique($contentTitleArr));
                        $max = max(count($contentIdArr),count($contentTitleArr));
                        for ($i=0;$i<$max;$i++){
                            //新表数据
                            $append = [
                                'content_id' => '',
                                'content_title' => '',
                                'old_log_id' => $item['id']
                            ];
                            if(isset($contentIdArr[$i]) && !empty($contentIdArr[$i])){
                                $append['content_id'] = $contentIdArr[$i];
                            }
                            if(isset($contentTitleArr[$i]) && !empty($contentTitleArr[$i])){
                                $append['content_title'] = $contentIdArr[$i];
                            }
                            $new = array_merge($left,$append);
                            if(!isset($new['content_id'])){
                                $new['content_id'] = '';
                            }
                            if(!isset($new['content_title'])){
                                $new['content_title'] = '';
                            }
                            //半年内数据
                            if($is_hot){
                                $insert['active'][] = $new;
                            }else{
                                $insert[$year][] = $new;
                            }
                        }
                    }else{
                        //新表数据
                        $append = [
                            'content_id' => '',
                            'content_title' => '',
                            'old_log_id' => $item['id']
                        ];
                        $new = array_merge($left,$append);
                        if(!isset($new['content_id'])){
                            $new['content_id'] = '';
                        }
                        if(!isset($new['content_title'])){
                            $new['content_title'] = '';
                        }
                        //半年内数据
                        if($is_hot){
                            $insert['active'][] = $new;
                        }else{
                            $insert[$year][] = $new;
                        }
                    }
                }
            }
            //插入新数据
            if(!empty($insert)){
                foreach ($insert as $key => $data){
                    if($key == 'active'){
                        $adminSearchLog->insertAll($data);
                    }else{
                         $adminSearchLog->table(AdminSearchLog::getTableName($key))->fetchSql(false)->insertAll($data);
                    }
                    usleep(500);
                }
            }
            echo $a . '本次循环'.'用时'.(microtime(true)-$stime ).PHP_EOL;
        }
    }

    /**
     * 迁移非热数据至年表中
     * @return void
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @throws Exception
     */
    private function moveNew()
    {
        $now = time();
        $adminSearchLog = new AdminSearchLog();
        //半年前时间戳
        $limit_time = bcsub($now,SECONDS_HALF_YEAR);
        //获取热搜表半年前最大时间
        $o_maxTime = $adminSearchLog->fetchSql(false)->where(['create_time' => ['ELT',$limit_time]])->order('create_time desc')->value('create_time');
        if(empty($o_maxTime)){
            echo '无数据'.PHP_EOL;
            return;
        }
        $year = date('Y',$o_maxTime);
        //获取最新年表最大time
        $n_maxTime = $adminSearchLog->table(AdminSearchLog::getTableName($year))->fetchSql(false)->value('max(create_time) as max');
        //如果无数据，取热表最小时间
        if(empty($n_maxTime)){
            $n_maxTime = $adminSearchLog->fetchSql(false)->where(['create_time' => ['ELT',$limit_time]])->order('create_time asc')->value('create_time');
        }
        if($n_maxTime >= $o_maxTime){
            echo '无数据'.PHP_EOL;
            return;
        }
        //每次处理一小时数据
        $limit = SECONDS_HOUR;
        $a = 0;
        $insert_flag = false;
        while($n_maxTime < $o_maxTime) {
            $stime = microtime(true);
            $min_time = $n_maxTime;
            $max_time = bcadd($min_time,$limit);
            $where = ['create_time' => ['between',[$min_time,$max_time]]];
            $data = self::getDataByTime($adminSearchLog,$where);
            //取完数据后重置起点
            $n_maxTime = $max_time;
            $a++;
            $insert = [];
            if(!empty($data)){
                foreach ($data as $item){
                    $year = date('Y',strtotime($item['create_time']));
                    $item['create_time'] = strtotime($item['create_time']);
                    $left = $item;
                    unset($left['id']);
                    if(empty($item['old_log_id'])){
                        $left['old_log_id'] = $item['id'];
                    }
                    $insert[$year][] = $left;
                }
            }
            //插入新数据
            if(!empty($insert)){
                foreach ($insert as $key => $add){
                    //分组处理
                    $add = array_chunk($add,1000);
                    foreach($add as $k => $insert){
                        $adminSearchLog->table(AdminSearchLog::getTableName($key))->insertAll($insert);
                        echo ($k+1) . '次插入'.PHP_EOL;
                        usleep(500);
                    }
                    $insert_flag = true;
                }
            }
            echo $a . '本次循环'.'用时'.(microtime(true)-$stime ).PHP_EOL;
        }
        if($insert_flag){
            echo '迁移完毕，开始删除数据'.PHP_EOL;
            $where = ['create_time' => ['ELT',$limit_time]];
            self::delDataByTime($adminSearchLog,$where);
        }
    }

    /**
     * 获取新表中最大原始log_id
     * @param string $date
     * @return int
     */
    public static function getMaxOldLogId($date = '')
    {
        if(empty($date)){
            $n_maxId = (new AdminSearchLog)->fetchSql(false)->where(['old_log_id' => ['GT',0]])->order('id desc')->value('old_log_id');
            if($n_maxId){
                return $n_maxId;
            }
            $date = date('Y');
        }
        $n_maxId = (new AdminSearchLog)->table(AdminSearchLog::getTableName($date))->fetchSql(false)->order('id desc')->value('old_log_id');
        if(empty($n_maxId) && $date > 2020){
            $new_max = self::getMaxOldLogId($date-1);
            if(!empty($new_max)){
                return $new_max;
            }
        }
        return $n_maxId;
    }

    /**
     * @param $adminSearchLog AdminSearchLog
     * @param $where
     * @return void
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public static function getDataByTime($adminSearchLog,$where)
    {
        $ids = $adminSearchLog->fetchSql(false)->where($where)->column('id');
        $data = [];
        if(!empty($ids)){
            $ids = array_chunk($ids,1000);
            foreach ($ids as $k => $item){
                echo $k.'次获取数据'.PHP_EOL;
                $res = $adminSearchLog->fetchSql(false)->where(['id' => ['in',$item]])->select()->toArray();
                $data = array_merge($data,$res);
            }
        }
        return $data;
    }

    /**
     * @param $adminSearchLog AdminSearchLog
     * @param $where
     * @return void
     * @throws DataNotFoundException
     * @throws DbException
     * @throws Exception
     * @throws ModelNotFoundException
     */
    public static function delDataByTime(AdminSearchLog $adminSearchLog, $where)
    {
        //获取热表半年前最小ID
        $data = $adminSearchLog->field('max(id) as max,min(id) as min')->fetchSql(false)->where($where)->find()->toArray();
        if(!empty($data)){
            $min = $data['min'];
            $max = $data['max'];
            $limit = 50;
            $i = 0;
            while($min < $max){
                $min_id = $min;
                $max_id = bcadd($min,$limit);
                $del_where = ['id' => ['between',[$min_id,$max_id]]];
                //删除热表数据 保留半年内数据
                $del = $adminSearchLog->where($del_where)->delete();
                if($del){
                    $i++;
                    echo '第'.$i.'次热表删除'.$del.'条数据'.PHP_EOL;
                    usleep(500);
                }
                //删完数据后重置起点
                $min = $max_id;
            }
        }
    }


}