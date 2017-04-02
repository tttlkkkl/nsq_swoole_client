<?php

/**
 * Class SynClient
 * 同步阻塞客户端，用于发布数据
 *
 * @datetime: 2017/3/29 11:57
 * @author: lihs
 * @copyright: ec
 */

namespace lib\client;

use lib\exception\ClientException;
use lib\log\Log;
use lib\log\LogInterface;
use lib\message\Unpack;
use lib\message\Packet;
use Swoole\Client as SwooleCilent;

class SynClient implements SynClientInterface {

    /**
     * 重试连接次数
     */
    const RETRY_COUNT = 5;
    /**
     * 话题
     * @var
     */
    private $topic;
    /**
     * 远程主机端口
     * @var
     */
    private $port;
    /**
     * 远程主机ip
     * @var
     */
    private $ip;

    /**
     * swoole 客户端对象
     * @var SwooleCilent
     */
    private $SwooleClient;

    /**
     * 服务端是否需要认证
     *
     * @var
     */
    private $authRequired;

    /**
     * 认证信息
     * @var
     */
    private $authSecret;

    /**
     * 日志打印
     *
     * @var Log
     */
    private $Log;

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
    private $userAgent = 'nsq_swoole_client_pub';

    /**
     * 是否已授权
     * @var
     */
    private $isAuth;

    /**
     * SynClient constructor.
     * @param SwooleCilent $SwooleClient
     * @param $topic
     * @param $ip
     * @param $port
     * @param LogInterface|null $Log
     * @param string $authSecret
     * @param string $hostName
     * @param string $clientId
     */
    public function __construct(
        SwooleCilent $SwooleClient,
        $ip,
        $port,
        $topic,
        $authSecret = '',
        LogInterface $Log = null,
        $hostName = '',
        $clientId = ''
    ) {
        $this->SwooleClient = $SwooleClient;
        $this->topic = $topic;
        $this->ip = $ip;
        $this->port = $port;
        $this->authSecret = $authSecret;
        $this->Log = $Log ?: new Log();
        $this->hostName = $hostName ?: gethostname();
        $this->clientId = ($clientId && is_string($clientId)) ? $clientId :
            (strpos($this->hostName, '.') ? substr($this->hostName, strpos($this->hostName, '.')) : $this->hostName);
        $this->init();
    }

    /**
     * 发布单条消息
     *
     * @param  $data
     * @param $topic
     * @return bool
     */
    public function pub($data, $topic = '') {
        if (!is_string($data)) {
            return false;
        }
        $topic = $topic ?: $this->topic;
        if (!$topic) {
            return false;
        }
        return $this->publish(Packet::pub($topic, $data));
    }

    /**
     * 发布多条消息
     *
     * @param array $data ['msg1','msg2']
     * @param $topic
     * @return bool
     */

    public function mPub(array $data, $topic = '') {
        $data = array_filter($data, function ($val) {
            return !is_null($val);
        });
        if (!$data) {
            return false;
        }
        $topic = $topic ?: $this->topic;
        if (!$topic) {
            return false;
        }
        return $this->publish(Packet::mPub($topic, $data));
    }

    /**
     * 关闭连接
     *
     * @return bool
     */
    public function close() {
        return $this->SwooleClient->isConnected() && $this->SwooleClient->close();
    }

    /**
     * 发布
     *
     * @param $data
     * @return bool
     * @throws ClientException
     */
    private function publish($data) {
        if (!$this->auth()) {
            throw new ClientException('授权错误', -1);
        }
        $result = $this->send($data);
        if (Unpack::isOk($result)) {
            return true;
        } elseif (Unpack::isError($result)) {
            throw new ClientException('错误:' . $result['msg']);
        } else {
            return false;
        }
    }

    /**
     * 数据收发
     *
     * @param $data
     * @return array|bool
     */
    private function send($data) {
        if (!$this->isConnected()) {
            return false;
        }
        if ($this->SwooleClient->send($data)) {
            return Unpack::getFrame($this->SwooleClient->recv());
        } else {
            return false;
        }
    }

    /**
     * 检查连接有效性,每5秒检查一次，5次仍然无法联通停止尝试
     *
     * @return bool
     */
    private function isConnected() {
        if ($this->SwooleClient->isConnected()) {
            return true;
        } else {
            for ($i = 1; $i < self::RETRY_COUNT; $i++) {
                $this->Log->info('重试第' . $i . '次连接...');
                if ($this->retryConnection()) {
                    return true;
                }
                sleep(1);
            }
            //第五次直接抛出错误
            if (!$this->retryConnection()) {
                throw new ClientException('连接失败!');
            }
        }
    }

    /**
     * 重试连接
     *
     * @return bool
     */
    private function retryConnection() {
        if ($this->SwooleClient->isConnected()) {
            return true;
        } else {
            return $this->SwooleClient->connect($this->ip, $this->port);
        }
    }


    /**
     *  初始化
     */
    private function init() {
        $this->SwooleClient->send(Packet::getMagic());
        $this->Log->info('约定通信协议');
        $msg = $this->send(Packet::identify([
            'client_id'           => $this->clientId,
            'hostname'            => $this->hostName,
            'user_agent'          => $this->userAgent,
            'heartbeat_interval'  => -1,//禁用心跳查询
            'feature_negotiation' => true
        ]));
        if (Unpack::isResponse($msg) && ($identify = json_decode($msg['msg'], true))) {
            //服务协商数据，检查是否需要授权
            if (is_array($identify) && isset($identify['auth_required']) && $identify['auth_required']) {
                $this->authRequired = true;
            } else {
                $this->authRequired = false;
            }
            $this->Log->info('服务协商成功!');
        } else {
            $this->Log->error('服务协商失败!');
        }
    }

    /**
     * 授权
     *
     * @param SwooleClient $client
     * @return bool
     */
    private function auth() {
        if ($this->isAuth) {
            return true;
        }
        if ($this->authRequired && $this->authSecret) {
            var_dump(Packet::auth($this->authSecret));
            $result = $this->send(Packet::auth($this->authSecret));
            if (Unpack::isError($result)) {
                $this->isAuth = true;
                if ($result['msg'] == 'E_INVALID AUTH Already set') {
                    return true;
                }
                $this->Log->error('授权错误:' . $result['msg'], -1);
                throw new ClientException('授权失败:' . $result['msg']);
            } elseif (Unpack::isResponse($result)) {
                $result = json_decode($result['msg'], true);
                if (isset($result['permission_count']) && $result['permission_count'] > 0) {
                    $this->isAuth = 1;
                    return true;
                }
            }
            return false;
        } elseif ($this->authRequired && !$this->authSecret) {
            return false;
        } else {
            return true;
        }
    }
}