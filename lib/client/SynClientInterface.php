<?php

/**
 * Class SynClientInterface
 * 同步阻塞客户端接口，用于tcp推送
 *
 * @datetime: 2017/3/29 11:58
 * @author: lihs
 * @copyright: ec
 */

namespace lib\client;


interface SynClientInterface {
    /**
     * 发布单条消息
     *
     * @param $data
     * @param $topic
     * @return bool
     */
    public function pub($data, $topic);

    /**
     * 发布多条消息
     *
     * @param array $data
     * @param $topic
     * @return bool
     */
    public function mPub(array $data, $topic);

    /**
     * 关闭连接
     *
     * @return mixed
     */
    public function close();
}