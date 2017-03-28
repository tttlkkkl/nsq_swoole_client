<?php

/**
 *
 * Date: 17-3-25
 * Time: 下午8:31
 * author :李华 yehong0000@163.com
 */
namespace lib;

use lib\client\ClientInterface;
use lib\exception\ClientException;
use Swoole\Client as SwooleCilent;
use lib\client\Client;
use lib\lookup\Lookup;

class NsqClient {
    public function __construct() {
        if (!extension_loaded('swoole')) {
            exit(-1);
        }
    }

    /**
     * 订阅
     *
     * @param $lookupHosts
     * @param $topic
     * @param string $channel
     *
     * @return bool
     * @throws exception\LookupException
     */
    public function sub($lookupHosts, $topic, $channel = '') {
        $Lookup = new Lookup($lookupHosts);
        $nsqdList = $Lookup->lookupHosts($topic);
        if (!$nsqdList || !isset($nsqdList['lookupHosts']) || !$nsqdList['lookupHosts'] || !is_array($nsqdList['lookupHosts'])) {
            throw new ClientException('未发现可用服务');
        }
        foreach ($nsqdList['lookupHosts'] as $host) {
            if (!$channel) {
                $channel = isset($nsqdList['topicChannel'][$host][0]) ? $nsqdList['topicChannel'][$host][0] : 'nsq_swoole_client';
            }
            $Client = new Client($topic, $channel);
            $this->init($Client, $host);
        }
    }

    /**
     * 启动一个客户端
     *
     * @param ClientInterface $Client
     * @param string $nsqdHost
     */
    public function init(ClientInterface $Client, $nsqdHost) {
        $SwooleClient = new SwooleCilent(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC);
        $SwooleClient->on("connect", [$Client, 'onConnect']);
        $SwooleClient->on("receive", [$Client, 'onReceive']);
        $SwooleClient->on("error", [$Client, 'onError']);
        $SwooleClient->on("close", [$Client, 'onClose']);
        $SwooleClient->set([
            'package_max_length'    => 1024 * 1024 * 2,
            'open_length_check'     => true,
            'package_length_type'   => 'N',
            'package_length_offset' => 0,       //第N个字节是包长度的值
            'package_body_offset'   => 4,       //第几个字节开始计算长度
        ]);
        if (($i = strpos($nsqdHost, ':')) === false) {
            $port = 4151;
        } else {
            $host = substr($nsqdHost, 0, $i);
            $port = substr($nsqdHost, $i + 1);
        }
        swoole_async_dns_lookup($host, function ($host, $ip) use ($SwooleClient, $port, $Client) {
            if (!$SwooleClient->connect($ip, $port)) {
                throw new ClientException('无法连接到:' . $host . ':' . $port);
            } else {
                $Client->setHost($ip, $port);
            }
        });
    }
}