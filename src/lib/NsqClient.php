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
use NsqClient\lib\process\Pool;
use Swoole\Process;

class NsqClient
{
    public function __construct()
    {
        if (!extension_loaded('swoole')) {
            exit(1);
        }
    }

    /**
     * 启动客户端
     *
     * @param ClientInterface $Client
     * @param $nsqdHost
     * @param int $min_woker_num 最小task进程数量
     * @param int $max_woker_num 最大进程数量
     * @param int $idle_seconds 空闲多少秒后杀死进程
     */
    public function init(ClientInterface $Client, $nsqdHost, $min_woker_num = 2, $max_woker_num = 10, $idle_seconds = 30)
    {
        (new Pool($Client, $nsqdHost, $min_woker_num, $max_woker_num, $idle_seconds))->init();
    }
}