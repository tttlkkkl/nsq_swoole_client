<?php

/**
 *
 * Date: 17-3-25
 * Time: 下午8:31
 * author :李华 yehong0000@163.com
 */

namespace NsqClient\lib;

use NsqClient\lib\client\ClientInterface;
use NsqClient\lib\client\SynClient;
use NsqClient\lib\exception\ClientException;
use Swoole\Client as SwooleCilent;

class NsqClient
{
    /**
     * 已实例化的客户端，ip:port 作为键值
     * @var
     */
    private $synClients;

    public function __construct()
    {
        if ( !extension_loaded('swoole') ) {
            exit(- 1);
        }
    }


    /**
     * 启动一个客户端
     *
     * @param ClientInterface $Client
     * @param string $nsqdHost
     */
    public function init( ClientInterface $Client, $nsqdHost )
    {
        $SwooleClient = new SwooleCilent(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC);
        $SwooleClient->on("connect", [ $Client, 'onConnect' ]);
        $SwooleClient->on("receive", [ $Client, 'onReceive' ]);
        $SwooleClient->on("error", [ $Client, 'onError' ]);
        $SwooleClient->on("close", [ $Client, 'onClose' ]);
        $SwooleClient->set([
            'package_max_length'    => 1024 * 1024 * 2,
            'open_length_check'     => true,
            'package_length_type'   => 'N',
            'package_length_offset' => 0,       //第N个字节是包长度的值
            'package_body_offset'   => 4,       //第几个字节开始计算长度
        ]);
        if ( ( $i = strpos($nsqdHost, ':') ) === false ) {
            $port = 4151;
        } else {
            $host = substr($nsqdHost, 0, $i);
            $port = substr($nsqdHost, $i + 1);
        }
        swoole_async_dns_lookup($host, function ( $host, $ip ) use ( $SwooleClient, $port, $Client ) {
            if ( !$SwooleClient->connect($ip, $port) ) {
                throw new ClientException('无法连接到:' . $host . ':' . $port);
            } else {
                $Client->setHost($ip, $port);
            }
        });
    }


    /**
     * 获取一个同步阻塞客户端实例
     *
     * @param $ip
     * @param $port
     * @param string $topic
     * @throws ClientException
     * @return SynClient
     */
    public function getSynClient( $ip, $port, $topic = '', $authSecret )
    {
        $key = $ip . ':' . $port;
        if ( !isset($this->synClients[ $key ]) ) {
            $SwooleClient = new SwooleCilent(SWOOLE_SOCK_TCP);
            $SwooleClient->set([
                'package_max_length'    => 1024 * 1024 * 2,
                'open_length_check'     => true,
                'package_length_type'   => 'N',
                'package_length_offset' => 0,       //第N个字节是包长度的值
                'package_body_offset'   => 4,       //第几个字节开始计算长度
            ]);
            if ( !$SwooleClient->connect($ip, $port, - 1) ) {
                throw new ClientException('无法连接到远程服务器', - 1);
            } else {
                $synClient = new SynClient($SwooleClient, $ip, $port, $topic, $authSecret);
                $this->synClients[ $key ] = $synClient;
            }
        }
        return $this->synClients[ $key ];
    }

    /**
     * 销毁一个同步客户端
     *
     * @param $ip
     * @param $port
     * @return bool
     */
    public function destroySynClient( $ip, $port )
    {
        $key = $ip . ':' . $port;
        if ( isset($this->synClients[ $key ]) && $this->synClients[ $key ]->close() ) {
            unset($this->synClients[ $key ]);
            return true;
        } else {
            return false;
        }
    }
}