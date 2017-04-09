<?php

/**
 * 重新排队策略
 * Date: 17-3-26
 * Time: 下午6:26
 * author :李华 yehong0000@163.com
 */
namespace lib\requeue;

use lib\exception\MessageException;
use lib\message\MessageInterface;
use lib\requeue\RequeueInterface;
class Requeue implements RequeueInterface
{
    /**
     * 最大排队次数
     * @var int
     */
    private $maxAttempts;

    /**
     * 排队超时时间
     * @var int
     */
    private $delay;

    /**
     * requeue constructor.
     *
     * @param int $maxAttempts 一条消息最大排队次数
     * @param int $delay       排队超时时间
     */
    public function __construct($maxAttempts = 10, $delay = 50)
    {
        $this->maxAttempts = $maxAttempts;
        $this->delay = $delay;

        if (!is_integer($this->delay) || $this->delay < 0) {
            throw new MessageException('错误的超时时间');
        }
    }

    /**
     * 判断一条消息是否可以被重新排队
     *
     * @param MessageInterface $msg
     *
     * @return int|null
     */
    public function shouldRequeue(MessageInterface $msg)
    {
        $attempts = $msg->getAttempts();
        return $attempts < $this->maxAttempts
            ? $this->delay
            : NULL;
    }
}