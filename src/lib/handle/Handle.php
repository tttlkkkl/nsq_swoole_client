<?php

/**
 *
 * Date: 17-3-26
 * Time: 下午8:44
 * author :李华 yehong0000@163.com
 */

namespace NsqClient\lib\handle;

use NsqClient\lib\log\Log;
use NsqClient\lib\message\MessageInterface;
use Swoole\Client as SwooleClient;

class Handle implements HandleInterface
{
    private $Log;

    public function __construct()
    {
        $this->Log = new Log();
    }

    /**
     * 收到消息之后会回调此函数，在自动完成的情况下如果此函数返回错误，或者抛出异常，消息都将会被立即重新排队。在非自动完成的情况下
     * 第二个参数时一个可执行的回调函数，调用回调函数来完成排队逻辑，回调函数接受一个参数 为 true 时完成消息消费，否则执行重新排队逻辑
     *
     * @param MessageInterface $message
     */
    public function handle( MessageInterface $message, \Closure $finish = NULL )
    {
        $this->Log->info('消息被消费');
        return true;
    }
}