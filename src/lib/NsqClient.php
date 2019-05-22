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
use NsqClient\lib\process\Pool;
use Swoole\Process;
use NsqClient\lib\lookup\Lookup;

class NsqClient
{
    public function __construct()
    {
        if (!extension_loaded('swoole')) {
            exit(1);
        }
    }

    /**
     * 以lookup为所有拥有指定topic的nsqd启动一个连接，和对应的处理进程池。启动的进程池数目和nsqd数目相等。
     *
     * @param ClientInterface $client
     * @param $lookUpAddress lookup 地址
     * @param int $min_wokers_num 进程池最小任务进程数
     * @param int $max_wokers_num 进程池最大进程数
     * @param int $idle_seconds 空闲超过这个时间后进程会被杀死
     * @throws ClientException
     */
    public function init(ClientInterface $client, $lookUpAddress, $min_wokers_num = 2, $max_wokers_num = 5, $idle_seconds = 30)
    {
        $lookUp = new Lookup($lookUpAddress);
        $hosts = $lookUp->lookupHosts($client->getTopic());
        $hosts = isset($hosts['lookupHosts']) ? $hosts['lookupHosts'] : [];
        if (empty($hosts)) {
            throw new ClientException('topic 尚未创建');
        }
        foreach ($hosts as $host) {
            $host = is_string($host) ? $host : '';
            (new Pool($client, $host, $min_wokers_num, $max_wokers_num, $idle_seconds))->init();
        }
        while ($ret = Process::wait()) {
            $pid = $ret['pid'];
            echo "process {$pid} existed\n";
        }
    }

    /**
     * @param ClientInterface $client
     * @param $address
     * @param int $min_wokers_num
     * @param int $max_wokers_num
     * @param int $idle_seconds
     * @throws ClientException
     */
    public function initNsqd(ClientInterface $client, $address, $min_wokers_num = 2, $max_wokers_num = 5, $idle_seconds = 30)
    {
        (new Pool($client, $address, $min_wokers_num, $max_wokers_num, $idle_seconds))->init();
        while ($ret = Process::wait()) {
            $pid = $ret['pid'];
            echo "process {$pid} existed\n";
        }
    }
}