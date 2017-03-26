<?php

/**
 *
 * Date: 17-3-25
 * Time: 下午9:54
 * author :李华 yehong0000@163.com
 */
namespace lib\client;

use lib\client\ClientInterface;
use Swoole\Client as SwooleClient;
use lib\message\Packet;
use lib\message\Unpack;
use lib\exception\ClientException;

class Client implements ClientInterface
{
    /**
     * 主机名
     * @var string
     */
    private $hostName;
    /**
     * 客户端标识
     *
     * @var string
     */
    private $clientId;
    /**
     * 订阅的话题
     * @var
     */
    private $topic;
    /**
     * 频道
     * @var
     */
    private $channel;

    /**
     * 客户端信息
     * @var string
     */
    private $userAgent = 'nsq_swoole_client';

    public function __construct($topic, $channel, $hostName = '', $clientId = '')
    {
        $this->hostName = $hostName ?: gethostname();
        $this->clientId = $clientId ?: strpos($this->hostName, '.') ? substr($hostName, strpos($hostName, '.')) : $this->hostName;
        $this->topic = $topic;
        $this->channel = $channel;
    }

    /**
     * 连接成功回调
     *
     * @param Client $client
     *
     * @return mixed
     */
    public function onConnect(SwooleClient $client)
    {
        //约定通信协议
        $client->send(Packet::getMagic());
        //协商相关配置
        $client->send(Packet::identify([
            'client_id'  => $this->clientId,
            'hostname'   => $this->hostName,
            'user_agent' => $this->userAgent
        ]));
        //订阅话题频道
        $client->send(Packet::sub($this->topic, $this->channel));
        $client->send(Packet::rdy(1));
    }

    /**
     * 连接失败回调
     *
     * @param Client $client
     *
     * @return mixed
     */
    public function onError(SwooleClient $client)
    {
        echo "error", "\n";
    }

    /**
     * 收到消息回调
     *
     * @param Client $client
     * @param $data
     *
     * @return mixed
     */
    public function onReceive(SwooleClient $client, $data)
    {
        echo '收到数据', "\n";
        var_dump(Unpack::getFrame($data));
    }

    /**
     * 关闭回调
     *
     * @param Client $client
     *
     * @return mixed
     */
    public function onClose(SwooleClient $client)
    {
        echo "关闭客户端\n";
    }
}