<?php
/**
 *
 * Date: 17-3-26
 * Time: 下午6:30
 * author :李华 yehong0000@163.com
 */

namespace NsqClient\lib\message;


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

    /**
     * 完成消息
     *
     * @return bool
     */
    public function finish();

    /**
     * 重新排队消息
     *
     * @param int $delay 延时时间 单位秒
     * @return bool
     */
    public function requeue($delay);

    /**
     * 重设消息到期时间--避免消息被服务器重新排队
     *
     * @return bool
     */
    public function touch();

    /**
     * 当执行完成finish() 或 requeue()时 此方法应该返回true
     *
     * @return bool
     */
    public function isHandle();
}