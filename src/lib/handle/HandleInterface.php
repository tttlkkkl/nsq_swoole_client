<?php
/**
 *
 * Date: 17-3-26
 * Time: 下午8:44
 * author :李华 yehong0000@163.com
 */

namespace lib\handle;


use lib\message\MessageInterface;

interface HandleInterface
{
    /**
     * 消息处理，消息业务处理
     * @param MessageInterface $message
     *
     * @return mixed
     */
    public function handle(MessageInterface $message);
}