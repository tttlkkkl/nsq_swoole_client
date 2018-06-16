<?php

/**
 *
 * Date: 17-3-26
 * Time: 下午8:44
 * author :李华 yehong0000@163.com
 */
namespace lib\handle;

use lib\log\Log;
use lib\message\MessageInterface;


class Handle implements HandleInterface
{
    private $Log;
    public function __construct()
    {
        $this->Log=new Log();
    }

    /**
     * 收到消息之后会回调此函数，如果此函数返回错误，或者抛出异常，消息都将会被排队
     *
     * @param MessageInterface $message
     */
    public function handle(MessageInterface $message)
    {
        $this->Log->info('消息被消费');
        return true;
    }
}