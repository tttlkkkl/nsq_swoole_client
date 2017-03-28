<?php
/**
 *
 * Date: 17-3-25
 * Time: 下午10:02
 * author :李华 yehong0000@163.com
 */

namespace lib\client;

use \Swoole\Client as SwooleClient;

interface ClientInterface
{
    /**
     * 连接成功回调
     *
     * @param Client $client
     *
     * @return mixed
     */
    public function onConnect(SwooleClient $client);

    /**
     * 连接失败回调
     *
     * @param Client $client
     *
     * @return mixed
     */
    public function onError(SwooleClient $client);

    /**
     * 收到消息回调
     *
     * @param Client $client
     * @param $data
     *
     * @return mixed
     */
    public function onReceive(SwooleClient $client, $data);

    /**
     * 关闭回调
     *
     * @param Client $client
     *
     * @return mixed
     */
    public function onClose(SwooleClient $client);

    /**
     * 设置断线重连参数
     *
     * @param $ip
     * @param $port
     * @return mixed
     */
    public function setHost($ip,$port);
}