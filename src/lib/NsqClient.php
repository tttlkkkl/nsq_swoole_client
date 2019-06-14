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
    /**
     * 进程池主进程
     *
     * @var
     */
    private $pools;

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
     * @param int $workNum 工作进程数
     * @throws ClientException
     */
    public function init(ClientInterface $client, $lookUpAddress, $workNum = 2)
    {
        $lookUp = new Lookup($lookUpAddress);
        $hosts = $lookUp->lookupHosts($client->getTopic());
        $hosts = isset($hosts['lookupHosts']) ? $hosts['lookupHosts'] : [];
        if (empty($hosts)) {
            throw new ClientException('topic 尚未创建');
        }
        foreach ($hosts as $host) {
            $host = is_string($host) ? $host : '';
            $mPid = (new Pool($client, $host, $workNum))->init();
            $this->pools[$mPid] = $host;
        }
        while ($ret = Process::wait()) {
            $pid = $ret['pid'];
            // 重启进程池
            if (isset($this->pools[$pid])) {
                $host = $this->pools[$pid];
                $client->getLog()->info('进程池master进程退出，重启进程池#' . $pid);
                $mPid = (new Pool($client, $host, $workNum))->init();
                $this->pools[$mPid] = $host;
                unset($this->pools[$pid]);
            }
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