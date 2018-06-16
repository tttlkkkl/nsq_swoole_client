<?php

/**
 *
 * Date: 17-3-25
 * Time: 下午8:37
 * author :李华 yehong0000@163.com
 */
namespace lib\log;
use lib\log\LogInterface;
class Log implements LogInterface
{
    /**
     * @param $msg
     */
    public function error($msg)
    {
        $this->log('error', $msg);
    }

    /**
     * @param $msg
     */
    public function warn($msg)
    {
        $this->log('warn', $msg);
    }

    /**
     * @param $msg
     */
    public function info($msg)
    {
        $this->log('info', $msg);
    }

    /**
     * @param $msg
     */
    public function debug($msg)
    {
        $this->log('debug', $msg);
    }

    /**
     * Log constructor.
     *
     * @param $level
     * @param $msg
     */
    private function log($level, $msg)
    {
        $msg =  $msg instanceof \Exception ? $msg->getMessage() : (string)$msg;
        fwrite(STDERR, sprintf('[%s] %s: %s%s', date('Y-m-d H:i:s'), strtoupper($level), $msg, PHP_EOL));
    }
}