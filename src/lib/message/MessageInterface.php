<?php
/**
 *
 * Date: 17-3-26
 * Time: 下午6:30
 * author :李华 yehong0000@163.com
 */

namespace lib\message;


interface MessageInterface
{
    /**
     * 获取消息内容
     * @return mixed
     */
    public function getMsg();

    /**
     * 获取消息id
     * @return mixed
     */
    public function getId();

    /**
     * 获取消息重复投递次数
     * @return mixed
     */
    public function getAttempts();

    /**
     * 获取消息时间戳
     * @return mixed
     */
    public function getTimestamp();
}