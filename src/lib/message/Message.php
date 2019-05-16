<?php
/**
 *
 * Date: 17-3-25
 * Time: 下午9:39
 * author :李华 yehong0000@163.com
 */

namespace NsqClient\lib\message;

use NsqClient\lib\dedupe\DedupeInterface;
use Swoole\Process;

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

    /**
     * @var Process
     */
    private $process;

    /**
     * 标记消息是否已被处理
     *
     * @var
     */
    private $isHandle;


    public function __construct($frame, Process $process)
    {
        $this->msg = isset($frame['msg']) ? $frame['msg'] : '';
        $this->id = isset($frame['id']) ? $frame['id'] : '';
        $this->attempts = isset($frame['attempts']) ? $frame['attempts'] : 0;
        $this->timestamp = isset($frame['timestamp']) ? $frame['timestamp'] : 0;
        $this->process = $process;
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

    /**
     * 消息完成
     *
     * @return bool
     */
    public function finish()
    {
        if ($this->process->write(Packet::fin($this->getId()))) {
            $this->isHandle = true;
            return true;
        }
        return false;
    }

    /**
     * 重新排队
     *
     * @param int $delay 延时时间，单位秒
     * @return bool
     */
    public function requeue($delay)
    {
        // 去除重复限制
        if ($this->process->write(Packet::req($this->getId(), $delay))) {
            $this->isHandle = true;
            return true;
        }
        return false;
    }

    /**
     * 重设消息到期时间--避免消息被服务器重新排队
     *
     * @return mixed
     */
    public function touch()
    {
        return $this->process->write(Packet::touch($this->getId()));
    }

    /**
     * @return bool
     */
    public function isHandle()
    {
        return $this->isHandle === true ? true : false;
    }
}