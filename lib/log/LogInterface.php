<?php

/**
 * 日志接口
 * Date: 17-3-25
 * Time: 下午9:58
 * author :李华 yehong0000@163.com
 */
namespace lib\log;
interface LogInterface
{
    /**
     * @param $msg
     */
    public function error($msg);

    /**
     * @param $msg
     */
    public function warn($msg);

    /**
     * @param $msg
     */
    public function info($msg);

    /**
     * @param $msg
     */
    public function debug($msg);

}