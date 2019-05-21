<?php
/**
 *
 * Date: 17-3-25
 * Time: 下午10:02
 * author :李华 yehong0000@163.com
 */

namespace NsqClient\lib\client;

use NsqClient\lib\dedupe\DedupeInterface;
use NsqClient\lib\handle\HandleInterface;
use NsqClient\lib\log\LogInterface;
use \Swoole\Client as SwooleClient;
use Closure;

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
    public function setHost($ip, $port);

    /**
     * @return string
     */
    public function getTopic();

    /**
     * @return string
     */
    public function getChannel();

    /**
     * @return LogInterface
     */
    public function getLog();

    /**
     * @return HandleInterface|Closure
     */
    public function getHandle();

    /**
     * 设置文本消息回调函数
     *
     * @param Closure $onTask
     * @return mixed
     */
    public function setTask(Closure $onTask);

    /**
     * @param $count
     * @return mixed
     */
    public function setRdy($count);
}