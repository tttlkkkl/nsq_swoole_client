<?php

/**
 *
 * Date: 17-3-25
 * Time: 下午9:54
 * author :李华 yehong0000@163.com
 */
namespace lib\client;

use lib\Dedupe\Dedupe;
use lib\Dedupe\DedupeInterface;
use lib\handle\Handle;
use lib\handle\HandleInterface;
use lib\log\Log;
use lib\log\LogInterface;
use lib\message\Message;
use lib\Requeue\Requeue;
use lib\Requeue\RequeueInterface;
use Swoole\Client as SwooleClient;
use lib\message\Packet;
use lib\message\Unpack;
use lib\exception\ClientException;

class Client implements ClientInterface {
    /**
     * 断开重连间隔，秒
     */
    const RECONNECT_INTERVAL = 5;
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

    /**
     * 日志类
     * @var Log|LogInterface
     */
    private $Log;

    /**
     * 消息去重
     * @var Dedupe|DedupeInterface
     */
    private $Dedupe;

    /**
     * 重新排队判断
     * @var Requeue|RequeueInterface
     */
    private $Requeue;

    /**
     * @var Handle|HandleInterface
     */
    private $Handle;
    /**
     * 初始ua数据
     * @var array
     */
    private $init;

    /**
     * ip用于断线重连
     * @var
     */
    private $ip;

    /**
     * 用于断线重连
     * @var
     */
    private $port;

    /**
     * 定时器
     * @var
     */
    private $timer;

    /**
     * 重试连接次数
     * @var
     */
    private $reconnects;

    public function __construct(
        $topic,
        $channel,
        HandleInterface $Handle = null,
        LogInterface $Log = null,
        DedupeInterface $Dedupe = null,
        RequeueInterface $Requeue = null,
        $hostName = '',
        $clientId = '') {
        $this->hostName = $hostName ?: gethostname();
        $this->clientId = ($clientId && is_string($clientId)) ? $clientId :
            (strpos($this->hostName, '.') ? substr($this->hostName, strpos($this->hostName, '.')) : $this->hostName);
        $this->topic = $topic;
        $this->channel = $channel;
        $this->Log = isset($Log) ? $Log : new Log();
        $this->Dedupe = isset($Dedupe) ? $Dedupe : new Dedupe();
        $this->Requeue = isset($Requeue) ? $Requeue : new Requeue();
        $this->Handle = isset($Handle) ? $Handle : new Handle();
        //响应次数和成功响应次数，用于判断是否订阅成功
        $this->init = [
            'ok'       => 0,
            'response' => 0
        ];
    }

    /**
     * 连接成功回调
     *
     * @param Client $client
     *
     * @return mixed
     */
    public function onConnect(SwooleClient $client) {
        //重置定时器
        $this->resetTimer($client);
        //约定通信协议
        $client->send(Packet::getMagic());
        $this->Log->debug('约定通信协议');
        //协商相关配置
        $client->send(Packet::identify([
            'client_id'  => $this->clientId,
            'hostname'   => $this->hostName,
            'user_agent' => $this->userAgent
        ]));
        $this->Log->debug('服务协商');
        //订阅话题频道
        $client->send(Packet::sub($this->topic, $this->channel));
        $this->Log->debug('订阅' . $this->topic . ':' . $this->channel);
    }

    /**
     * 连接失败回调
     *
     * @param Client $client
     *
     * @return mixed
     */
    public function onError(SwooleClient $client) {
        $this->Log->error('服务器连接失败');
    }

    /**
     * 收到消息回调
     *
     * @param Client $client
     * @param $data
     *
     * @return mixed
     */
    public function onReceive(SwooleClient $client, $data) {
        $frame = Unpack::getFrame($data);
        $this->init['response'] += 1;
        if (Unpack::isHeartbeat($frame)) {
            $client->send(Packet::nop());
            $this->Log->debug('心跳查询');
        } elseif (Unpack::isOk($frame)) {
            $this->init['ok'] += 1;
            $this->Log->debug('成功响应:' . $frame['msg']);
            if (2 === $this->init['response'] && 2 === $this->init['response']) {
                $this->Log->info('订阅成功，开始第一条消费');
            }
            $client->send(Packet::rdy(1));
        } elseif (Unpack::isError($frame)) {
            $this->Log->warn('错误响应' . $frame['msg']);
        } elseif (Unpack::isMessage($frame)) {
            $this->Log->debug('收到消费消息:' . $frame['msg']);
            $this->handleMessage($client, $frame);
            $client->send(Packet::rdy(1));
        } else {
            $this->Log->warn('未知的响应');
        }
    }

    /**
     * 关闭回调
     *
     * @param Client $client
     *
     * @return mixed
     */
    public function onClose(SwooleClient $client) {
        $this->Log->warn('连接断开...');
        $this->resetTimer($client);
        $this->timer || $this->timer = swoole_timer_tick(self::RECONNECT_INTERVAL * 1000, function () use ($client) {
            if (!$client->isConnected()) {
                $this->reconnects += 1;
                $this->Log->info('正在尝试重连...第' . $this->reconnects . '次...');
                $client->connect($this->ip, $this->port);
            }
        });
    }

    /**
     * 重置定时器
     *
     * @param SwooleClient $client
     */
    private function resetTimer(SwooleClient $client) {
        $this->reconnects = 0;
        if ($this->timer && $client->isConnected()) {
            swoole_timer_clear($this->timer);
            $this->timer = 0;
        }
    }

    /**
     * 设置目标端点的连接信息,用于断线重连
     *
     * @param $ip
     * @param $port
     */
    public function setHost($ip, $port) {
        $this->ip = $ip;
        $this->port = $port;
    }

    /**
     * 消息处理
     *
     * @param SwooleClient $client
     * @param array $frame
     */
    private function handleMessage(SwooleClient $client, array $frame) {
        $message = new Message($frame);
        //消息重复
        if ($this->Dedupe->add($this->topic, $this->channel, $message)) {
            $this->Log->debug('重复消息：' . json_encode($frame));
            $client->send(Packet::fin($message->getId()));
            $client->send(Packet::rdy(1));
        } else {
            try {
                $handle = $this->Handle->handle($message);
            } catch (\Exception $E) {
                $handle = false;
            }
            if ($handle) {
                $this->Log->info('消息处理完毕:' . json_encode($frame));
                $client->send(Packet::fin($message->getId()));
                $client->send(Packet::rdy(1));
                $this->Log->debug('准备接收消息');
            } else {
                $this->Log->error('消息处理出错:' . json_encode($frame));
                if (($timeout = $this->Requeue->shouldRequeue($message)) !== null) {
                    if ($client->send(Packet::req($message->getId(), $timeout))) {
                        $this->Dedupe->clear($this->topic, $this->channel, $message);
                        $this->Log->info('消息重新排队:' . json_encode($frame));
                    } else {
                        $this->Log->debug('消息排队失败:' . json_encode($frame));
                    }
                } else {
                    $this->Log->info('消息被丢弃:' . json_encode($frame));
                }
            }
        }
    }
}