<?php
/**
 * @author jseo.qiao
 * @date 2021年11月02日 7:49 下午
 */


namespace think;


class RankLog
{
    //当前系统时间,毫秒级
    private  $timeStampCurrent;
    //当前文件名称
    private $fileNameCurrent;
    //日志目录
    private $filePathBase = '/data/weblog/RankLog/';
    //文件记录唯一标识
    private $fileTag;
    //进程id
    private $pid = 0;
    //文件句柄
    private $fp;
    //日志数组
    private $logArray = [];
    //是否记录运行时间差
    private $runTimeStatus = true;
    //格式时间date
    private $dateFormat;

    public function __construct($fileTag,$path = null)
    {
        $this->setFileTag($fileTag);
        $this->setFilePathBase($path);
        $this->setPid();

    }


    public function start()
    {
        $msec = $this->getMsec();
        $this->setTimeStampCurrent($msec);
        $logPath = $this->getPath($msec);
        if ($logPath != $this->fileNameCurrent || empty($this->fileNameCurrent)) {
            $this->closefile($this->fp);
            $this->fileNameCurrent = $logPath;
            $this->fp = $this->getFp();
        }
    }

    public function logAPPEND($str)
    {
        $this->logArray[] = $str;
    }


    public function write()
    {
        $tpmLoginArray = [];
        $tpmLoginArray[] = $this->dateFormat;
        if ($this->runTimeStatus) {
            $endMsec = $this->getMsec();
            $diffTime = $endMsec - $this->timeStampCurrent ;
            $tpmLoginArray[] = $endMsec;
            $tpmLoginArray[] = $diffTime;
        }
        $tpmLoginArray  = array_merge($tpmLoginArray,$this->logArray);
        $str = implode('|', $tpmLoginArray).PHP_EOL;
        unset($tpmLoginArray);
        $this->clearLogArray();
        try {
            if (flock($this->fp, LOCK_EX)) {
                fwrite($this->fp, $str);
                flock($this->fp, LOCK_UN);
            }
        } catch ( Exception $e) {
            echo $e->getMessage();
        }

    }


    public function getPath($time = null)
    {
        $time = $time ? $time : $this->timeStampCurrent;
        $dateFormat = date("Y-m-d-H:i:s",$time/1000);
        $this->setDateFormat($dateFormat);
        $logTimeTag = substr($dateFormat,0,13);
        $date = $this->fileTag.'_'.$logTimeTag.'_'.($this->pid%10);
        return $this->filePathBase.$date . '.log';

    }

    public function getFp()
    {
        $fp = '';
        try {
            $fp = fopen($this->fileNameCurrent,'a');
        }catch (Exception $exception){
            echo $exception->getMessage();
        }
        return $fp;
    }

    public function closefile()
    {
        if ($this->fp) {
            flock($this->fp, LOCK_UN);
            fclose($this->fp);
        }
    }

    public function getLogArray()
    {
        return $this->logArray;
    }

    public function clearLogArray()
    {
        unset($this->logArray);
        $this->logArray = [];
    }

    /**
     * @param mixed $timeStampCurrent
     */
    public function setTimeStampCurrent($timeStampCurrent)
    {
        $this->timeStampCurrent = $timeStampCurrent;
    }

    /**
     * @return mixed
     */
    public function getTimeStampCurrent()
    {
        return $this->timeStampCurrent;
    }

    /**
     * @param mixed $fileNameCurrent
     */
    public function setFileNameCurrent($fileNameCurrent)
    {
        $this->fileNameCurrent = $fileNameCurrent;
    }

    /**
     * @param mixed $filePathBase
     */
    public function setFilePathBase($filePathBase)
    {
        if ($filePathBase) {
            $this->filePathBase = $filePathBase;
        }
        if (!is_dir($this->filePathBase)) {
            $res = @mkdir($this->filePathBase);
            if (!$res) {
                echo "{$this->filePathBase}创建文件失败";
            }
        }
    }

    /**
     * @param mixed $fileTag
     */
    public function setFileTag($fileTag)
    {
        $this->fileTag = $fileTag;
    }

    /**
     * @param int $pid
     */
    public function setPid()
    {
        $this->pid = posix_getpid();
    }

    /**
     * @param bool $runTimeStatus
     */
    public function setRunTimeStatus($runTimeStatus)
    {
        $this->runTimeStatus = $runTimeStatus;
    }

    /**
     * @param array $dateFormat
     */
    public function setDateFormat($dateFormat)
    {
        $this->dateFormat = $dateFormat;
    }

    public function __destruct()
    {
        if ($this->fp) {
            fclose($this->fp);
        }
    }

    public function getMsec(){//返回毫秒时间戳
        $arr = explode(' ',microtime());
        $hm = 0;
        foreach($arr as $v){
            $hm += floor($v * 1000);
        }
        return $hm;
    }

}