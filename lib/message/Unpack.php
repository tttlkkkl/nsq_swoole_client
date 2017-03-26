<?php

/**
 *  消息分包
 *
 * Date: 17-3-25
 * Time: 下午8:40
 * author :李华 yehong0000@163.com
 */
namespace lib\message;

use lib\exception\MessageException;

class Unpack
{
    /**
     * 消息类型
     */
    const FRAME_TYPE_RESPONSE = 0;//响应
    const FRAME_TYPE_ERROR    = 1;//错误响应
    const FRAME_TYPE_MESSAGE  = 2;//消息响应

    /**
     * 心跳查询
     */
    const HEARTBEAT = '_heartbeat_';

    /**
     * 成功响应体
     */
    const OK = 'OK';

    public static function getFrame($receive = '')
    {
        $frame = [];
        $type = self::getInt(substr($receive, 4, 4));
        $frame['type'] = $type;
        switch ($type) {
            case self::FRAME_TYPE_RESPONSE:
                $frame['msg'] = substr($receive, 8);
                break;
            case self::FRAME_TYPE_ERROR:
                $frame['msg'] = substr($receive, 8);
                break;
            case self::FRAME_TYPE_MESSAGE:
                //纳秒级时间戳
                $frame['timestamp'] = self::getLong(substr($receive, 8, 8));
                //尝试次数
                $frame['attempts'] = self::getShort(substr($receive, 16, 2));
                //消息id
                $frame['id'] = self::getString(substr($receive, 18, 16));
                //消息内容
                $frame['msg'] = substr($receive, 34);
                break;
            default:
                throw new MessageException('未知的消息类型', -1);
        }
        return $frame;
    }

    /**
     * 消息类型检查
     *
     * @param array $frame
     * @param $type
     * @param $response
     *
     * @return bool
     */
    private static function checkMessage(array $frame, $type, $response = null)
    {
        return isset($frame['type'], $frame['msg'])
        && $frame['type'] === $type
        && ($response === NULL || $frame['msg'] === $response);
    }

    /**
     * 是否是响应消息
     *
     * @param $frame
     *
     * @return bool
     */
    public static function isResponse($frame)
    {
        return self::checkMessage($frame, self::FRAME_TYPE_RESPONSE);
    }

    /**
     * 是否是消费消息
     *
     * @param $frame
     *
     * @return bool
     */
    public static function isMessage($frame)
    {
        return self::checkMessage($frame, self::FRAME_TYPE_MESSAGE);
    }

    /**
     * 是否是心跳检查
     *
     * @param $frame
     *
     * @return bool
     */
    public static function isHeartbeat($frame)
    {
        //确切的说心跳检查不属于响应类型。也没有第4-8字节表示消息类型，但是此处为了处理方便将其归结为响应类型
        return self::checkMessage($frame, self::FRAME_TYPE_RESPONSE, self::HEARTBEAT);
    }

    /**
     * 是否是成功响应
     *
     * @param $frame
     *
     * @return bool
     */
    public static function isOk($frame)
    {
        return self::checkMessage($frame, self::FRAME_TYPE_RESPONSE, self::OK);
    }

    /**
     * 是否是错误响应
     *
     * @param $frame
     *
     * @return bool
     */
    public static function isError($frame)
    {
        return self::checkMessage($frame, self::FRAME_TYPE_ERROR);
    }

    /**
     * 获取4字节整型数据
     *
     * @param $param
     *
     * @return int
     */
    private static function getInt($param)
    {
        $param = unpack('N', $param);
        $data = reset($param);
        if (PHP_INT_SIZE !== 4) {
            $data = sprintf('%u', $data);
        }
        return intval($data);
    }

    /**
     * @param $param
     *
     * @return mixed
     */
    private static function getShort($param)
    {
        $param = unpack('n', $param);
        return reset($param);
    }

    /**
     * 解包8位长整型
     *
     * @param $param
     *
     * @return string
     */
    private static function getLong($param)
    {
        $hi = unpack('N', substr($param, 0, 4));
        $lo = unpack('N', substr($param, 4));
        $hi = sprintf("%u", $hi[1]);
        $lo = sprintf("%u", $lo[1]);

        return \bcadd(bcmul($hi, "4294967296"), $lo);
    }

    /**
     * 解包16位字符串
     *
     * @param $param
     *
     * @return string
     */
    private static function getString($param)
    {
        $temp = unpack("c16chars", $param);
        $out = "";
        foreach ($temp as $v) {
            if ($v > 0) {
                $out .= chr($v);
            }
        }
        return $out;
    }
}