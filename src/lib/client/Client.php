<?php

/**
 * 异步非阻塞客户端，用于消费
 *
 * Date: 17-3-25
 * Time: 下午9:54
 * author :李华 yehong0000@163.com
 */

namespace NsqClient\lib\client;

use NsqClient\lib\dedupe\Dedupe;
use NsqClient\lib\dedupe\DedupeInterface;
use NsqClient\lib\exception\ClientException;
use NsqClient\lib\handle\Handle;
use NsqClient\lib\handle\HandleInterface;
use NsqClient\lib\log\Log;
use NsqClient\lib\log\LogInterface;
use NsqClient\lib\message\Message;
use NsqClient\lib\requeue\Requeue;
use NsqClient\lib\requeue\RequeueInterface;
use Swoole\Client as SwooleClient;
use NsqClient\lib\message\Packet;
use NsqClient\lib\message\Unpack;
use Swoole\Coroutine\Channel;
use Swoole\Process;
use Closure;

class Client implements ClientInterface
{
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
     * 客户端信息
     * @var string
     */
    private $userAgent = 'nsq_swoole_client_sub';

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
     * 日志类
     * @var Log|LogInterface
     */
    private $Log;

    /**
     * 重新排队判断
     * @var Requeue|RequeueInterface
     */
    public $Requeue;

    /**
     * @var Handle|HandleInterface|Closure
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

    /**
     * 服务端是否需要认证
     *
     * @var
     */
    private $authRequired;

    /**
     * 认证信息
     * @var string
     */
    private $authSecret;

    /**
     * 是否已授权
     * @var
     */
    private $isAuth;

    /**
     * @var bool
     */
    public $finishAuto;

    /**
     * 额外得服务协商数据
     *
     * @var
     */
    private $identify;

    /**
     * @var Closure;
     */
    private $task;

    /**
     * Client constructor.
     * @param $topic
     * @param $channel
     * @param string $authSecret
     * @param HandleInterface|Closure|null $Handle
     * @param bool $finishAuto 是否自动完成，true:当handel函数返回true时finish否则如果返回false或者异常将会重新排队
     * @param LogInterface|NULL $Log
     * @param RequeueInterface|NULL $Requeue
     * @param string $hostName
     * @param string $clientId
     * @param array $identify 自定义的服务协商数据
     * @throws \NsqClient\lib\exception\MessageException
     */
    public function __construct(
        $topic,
        $channel,
        $authSecret = '',
        $handle = NULL,
        $finishAuto = true,
        LogInterface $Log = NULL,
        RequeueInterface $Requeue = NULL,
        $identify = []
    )
    {
        $this->hostName = gethostname();
        if (!is_string($this->hostName)) {
            $this->hostName = '';
        }
        $this->topic = $topic;
        $this->channel = $channel;
        $this->finishAuto = $finishAuto;
        $this->Log = isset($Log) ? $Log : new Log();
        $this->Requeue = isset($Requeue) ? $Requeue : new Requeue();
        if (isset($handle)) {
            if ($handle instanceof HandleInterface || $handle instanceof Closure) {
                $this->Handle = $handle;
            } else {
                throw new ClientException('参数错误 handle 必须是 HandleInterface 对象或者一个闭包。');
            }
        } else {
            $this->Handle = new Handle();
        }
        $this->authSecret = $authSecret;
        //默认需要授权，服务协商成功后确认实际是否需要授权
        $this->authRequired = true;
        //响应次数和成功响应次数，用于判断是否订阅成功
        $this->init = [
            'ok'       => 0,
            'response' => 0
        ];
        $this->identify = $identify;
    }

    /**
     * 连接成功回调
     *
     * @param SwooleClient $client
     * @return mixed|void
     */
    public function onConnect(SwooleClient $client)
    {
        //重置定时器
        $this->resetTimer($client);
        //约定通信协议
        $client->send(Packet::getMagic());
        $this->Log->debug('约定通信协议');
        //协商相关配置
        $identify = array_merge([
            'client_id'           => $this->clientId,
            'hostname'            => $this->hostName,
            'user_agent'          => $this->userAgent,
            'feature_negotiation' => true
        ], $this->identify);
        $client->send(Packet::identify($identify));
        $this->Log->debug('服务协商');
    }

    /**
     * 连接失败回调
     *
     * @param SwooleClient $client
     * @return mixed|void
     */
    public function onError(SwooleClient $client)
    {
        $this->Log->error('服务器连接失败');
    }

    /**
     * 收到消息回调
     *
     * @param SwooleClient $client
     * @param $data
     * @return int|mixed
     * @throws \NsqClient\lib\exception\MessageException
     */
    public function onReceive(SwooleClient $client, $data)
    {
        $frame = Unpack::getFrame($data);
        $this->init['response'] += 1;
        if (Unpack::isHeartbeat($frame)) {
            $client->send(Packet::nop());
            $this->Log->debug('心跳查询');
            return 1;
        } elseif (Unpack::isOk($frame)) {
            $this->init['ok'] += 1;
            $this->Log->debug('成功响应:' . $frame['msg']);
            $response = $this->authRequired ? 3 : 2;
            if ($response === $this->init['response'] && 1 === $this->init['ok']) {
                $this->Log->info('订阅成功，开始第一条消费');
                $client->send(Packet::rdy(1));
                return 1;
            }
        } elseif (Unpack::isError($frame)) {
            $this->Log->warn('错误响应' . $frame['msg']);
            return 1;
        } elseif (Unpack::isMessage($frame)) {
            $this->Log->info('收到消费消息:' . $frame['msg']);
            call_user_func($this->task, $client, serialize($frame));
            return 1;
        } elseif (Unpack::isResponse($frame)) {
            $identify = json_decode($frame['msg'], true);
            if (isset($identify['auth_required'])) {
                //服务协商数据，检查是否需要授权
                $this->Log->info('收到服务协商数据:' . $frame['msg']);
                $this->authRequired = $identify['auth_required'] ? true : false;
                $this->isAuth = $this->authRequired ? false : true;
                //订阅
                $this->sub($client);
            }
            //收到正确的授权结果
            if (isset($identify['permission_count']) && $identify['permission_count'] > 0) {
                $this->Log->info('收到授权结果信息:' . $frame['msg']);
                $this->isAuth = true;
            }
            return 1;
        } else {
            $this->Log->warn('未知的响应');
        }
        return 0;
    }

    /**
     * 服务订阅
     *
     * @param SwooleClient $client
     */
    private function sub(SwooleClient $client)
    {
        if ($this->auth($client)) {
            //订阅话题频道
            $client->send(Packet::sub($this->topic, $this->channel));
            $this->Log->debug('订阅' . $this->topic . ':' . $this->channel);
        } else {
            $this->Log->error('授权信息缺失');
        }
    }

    /**
     * 授权
     *
     * @param SwooleClient $client
     * @return bool
     */
    private function auth(SwooleClient $client)
    {
        if ($this->isAuth) {
            return true;
        }
        if ($this->authRequired && $this->authSecret) {
            return $client->send(Packet::auth($this->authSecret));
        } elseif ($this->authRequired && !$this->authSecret) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * 关闭时的回调
     *
     * @param SwooleClient $client
     * @return mixed|void
     */
    public function onClose(SwooleClient $client)
    {
        $this->Log->warn('连接断开...');
        //设置参数，等待重启
        $this->init['ok'] = $this->init['response'] = 0;
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
    private function resetTimer(SwooleClient $client)
    {
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
    public function setHost($ip, $port)
    {
        $this->ip = $ip;
        $this->port = $port;
    }


    /**
     * @return string
     */
    public function getTopic()
    {
        return $this->topic;
    }

    /**
     * @return string
     */
    public function getChannel()
    {
        return $this->channel;
    }

    /**
     * @return Log|LogInterface
     */
    public function getLog()
    {
        return $this->Log;
    }

    /**
     * @return Handle|HandleInterface|NULL
     */
    public function getHandle()
    {
        return $this->Handle;
    }

    /**
     * @param Closure $task
     */
    public function setTask(Closure $onTask)
    {
        $this->task = $onTask;
    }

}