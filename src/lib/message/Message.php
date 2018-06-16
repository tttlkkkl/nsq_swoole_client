<?php
/**
 *
 * Date: 17-3-25
 * Time: 下午9:39
 * author :李华 yehong0000@163.com
 */

namespace lib\message;

class Message implements MessageInterface
{
    /**
     * 消息内容
     * @var
     */
    private $msg;
    /**
     * 消息id
     * @var
     */
    private $id;
    /**
     * 消息已重新排队次数
     * @var
     */
    private $attempts;
    /**
     * 纳秒级时间戳
     * @var
     */
    private $timestamp;

    public function __construct($frame)
    {
        $this->msg = $frame['msg'];
        $this->id = $frame['id'];
        $this->attempts = $frame['attempts'];
        $this->timestamp = $frame['timestamp'];
    }

    /**
     * 获取消息内容
     * @return mixed
     */
    public function getMsg()
    {
        return $this->msg;
    }

    /**
     * 获取消息id
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * 获取消息重复投递次数
     * @return mixed
     */
    public function getAttempts()
    {
        return $this->attempts;
    }

    /**
     * 获取消息时间戳
     * @return mixed
     */
    public function getTimestamp()
    {
        return $this->timestamp;
    }
}