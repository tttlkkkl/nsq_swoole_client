<?php

/**
 * 重复消息删除策略
 * Date: 17-3-26
 * Time: 下午6:44
 * author :李华 yehong0000@163.com
 */
namespace lib\dedupe;
use lib\message\MessageInterface;
interface DedupeInterface
{
    /**
     * 添加消息到本地
     * @param $topic
     * @param $channel
     * @param MessageInterface $msg
     *
     * @return mixed
     */
    public function add($topic, $channel, MessageInterface $msg);

    /**
     * 将消息从本地清除
     * @param $topic
     * @param $channel
     * @param MessageInterface $msg
     *
     * @return mixed
     */
    public function clear($topic, $channel, MessageInterface $msg);
}