<?php

/**
 *
 * Date: 17-3-25
 * Time: 下午8:41
 * author :李华 yehong0000@163.com
 */
namespace lib\message;
class Packet {
    /**
     * 通信协议
     */
    const MAGIC = "  V2";

    /**
     * 获取通信协议
     *
     * @return string
     */
    public static function getMagic() {
        return self::MAGIC;
    }

    /**
     * 服务协商
     *
     * @param array $params
     *
     * @return string
     */
    public static function identify(array $params) {
        $message = json_encode($params);
        $cmd = self::packing('IDENTIFY');
        $size = pack('N', strlen($message));
        //$message=pack('N',$message);
        return $cmd . $size . $message;
    }

    /**
     * 订阅话题
     *
     * @param $topic
     * @param $channel
     *
     * @return string
     */
    public static function sub($topic, $channel) {
        return self::packing('SUB', $topic, $channel);
    }

    /**
     * 发布消息到话题
     *
     * @param string $topic
     * @param string $message
     *
     * @return string
     */
    public static function pub($topic, $message) {
        $cmd = self::packing('PUB', $topic);
        $size = pack('N', strlen($message));
        return $cmd . $size . $message;
    }

    /**
     * 批量推送消息到话题
     *
     * @param $topic
     * @param array $message
     *
     * @return string
     */
    public static function mPub($topic, array $message) {
        $cmd = self::packing('MPUB', $topic);
        $num = pack('N', count($message));
        $bodyLen = 0;
        $body = '';
        foreach ($message as $msg) {
            $len = strlen($msg);
            $bodyLen += $len;
            $body .= pack('N', $len) . $msg;
        }
        return $cmd . pack('N', $bodyLen) . $num . $body;
    }

    /**
     * 准备接收$count条消息
     *
     * @param $count
     *
     * @return string
     */
    public static function rdy($count) {
        return self::packing('RDY', $count);
    }

    /**
     * 处理完成一个消息
     *
     * @param $messageId
     *
     * @return string
     */
    public static function fin($messageId) {
        return self::packing('FIN', $messageId);
    }

    /**
     * 重新排队
     *
     * @param $messageId
     * @param $timeout
     *
     * @return string
     */
    public static function req($messageId, $timeout) {
        return self::packing('REQ', $messageId, $timeout);
    }

    /**
     * 重置消息传输时间
     *
     * @param $messageId
     *
     * @return string
     */
    public static function touch($messageId) {
        return self::packing('TOUCH', $messageId);
    }

    /**
     * 清除，不再收发消息
     *
     * @return string
     */
    public static function cls() {
        return self::packing('CLS');
    }

    /**
     * 心跳反馈
     *
     * @return string
     */
    public static function nop() {
        return self::packing('NOP');
    }

    /**
     * 授权请求
     *
     * @param $secret 授权码
     *
     * @return string
     */
    public static function auth($secret) {
        $cmd = self::packing('AUTH');
        $size = pack('N', strlen($secret));
        return $cmd . $size . $secret;
    }

    /**
     * 命令前缀
     *
     * @return string
     */
    public static function packing() {
        $args = func_get_args();
        $cmd = array_shift($args);
        return sprintf("%s %s%s", $cmd, implode(' ', $args), "\n");
    }
}