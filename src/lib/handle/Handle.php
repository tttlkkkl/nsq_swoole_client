<?php

/**
 * 消息处理示例
 *
 * Date: 17-3-26
 * Time: 下午8:44
 * author :李华 yehong0000@163.com
 */

namespace NsqClient\lib\handle;

use NsqClient\lib\message\MessageInterface;
use Swoole\Client as SwooleClient;

class Handle implements HandleInterface
{
    private $Log;

    public function __construct()
    {
    }

    /**
     * @param MessageInterface $message
     * @return bool|mixed
     */
    public function handle( MessageInterface &$message )
    {
        $message->finish();
        return true;
    }
}