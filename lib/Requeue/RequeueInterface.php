<?php

/**
 * 重新排队策略
 * Date: 17-3-26
 * Time: 下午6:25
 * author :李华 yehong0000@163.com
 */
namespace lib\Requeue;
use lib\message\MessageInterface;
interface RequeueInterface
{
    /**
     * 指定一条消息是否应该被重新排队
     *
     * @param MessageInterface $msg
     *
     * @return bool
     */
    public function shouldRequeue(MessageInterface $msg);
}