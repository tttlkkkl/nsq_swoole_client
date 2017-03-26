<?php

/**
 *
 * Date: 17-3-25
 * Time: 下午8:31
 * author :李华 yehong0000@163.com
 */
namespace lib;

use lib\client\ClientInterface;
use Swoole\Client as SwooleCilent;

class NsqClient
{
    public function __construct()
    {
        if (!extension_loaded('swoole')) {
            exit(-1);
        }
    }

    /**
     * 启动一个客户端
     *
     * @param ClientInterface $Client
     * @param $hosts
     */
    public function init(ClientInterface $Client, $hosts)
    {
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
        if (($i = strpos($hosts, ':')) === false) {
            $port = 4151;
        } else {
            $host = substr($hosts, 0, $i);
            $port = substr($hosts, $i + 1);
        }
        swoole_async_dns_lookup($host, function ($host, $ip) use ($SwooleClient, $port) {
            $SwooleClient->connect($ip, $port);
        });
    }
}