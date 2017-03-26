<?php

/**
 *
 * Date: 17-3-26
 * Time: 下午6:45
 * author :李华 yehong0000@163.com
 */
namespace lib\Dedupe;

use lib\message\MessageInterface;

class Dedupe
{
    /**
     * 数据存储数组
     * @var
     */
    private $map;
    /**
     * 保存的数组长度
     * @var
     */
    private $size;

    public function __construct($size = 10000)
    {
        $this->size = $size;
    }

    /**
     * 添加到本地
     *
     * @param $topic
     * @param $channel
     * @param MessageInterface $msg
     *
     * @return mixed
     */
    public function add($topic, $channel, MessageInterface $msg)
    {
        $hashed = $this->hash($topic, $channel, $msg);
        $this->map[$hashed['index']] = $hashed['content'];
        return $hashed['seen'];
    }

    /**
     * 从本地删除
     *
     * @param $topic
     * @param $channel
     * @param MessageInterface $msg
     */
    public function clear($topic, $channel, MessageInterface $msg)
    {
        $hashed = $this->hash($topic, $channel, $msg);
        if ($hashed['seen']) {
            unset($this->map[$hashed['index']]);
        }
    }

    /**
     * @param $topic
     * @param $channel
     * @param MessageInterface $msg
     *
     * @return array
     */
    private function hash($topic, $channel, MessageInterface $msg)
    {
        $element = "$topic:$channel:" . $msg->getMsg();
        $hash = hash('adler32', $element, TRUE);
        list(, $val) = unpack('N', $hash);
        $index = $val % $this->size;
        $content = md5($element);
        $seen = isset($this->map[$index]) && $this->map[$index] === $content;
        return ['index' => $index, 'content' => $content, 'seen' => $seen];
    }
}