<?php
/**
 * 编码示例
 *
 * Date: 17-3-25
 * Time: 下午8:31
 * author :李华 yehong0000@163.com
 */
require '/app/vendor/autoload.php';

use NsqClient\lib\NsqClient;
use NsqClient\lib\client\Client;
use NsqClient\lib\lookup\Lookup;
use NsqClient\lib\exception\ClientException;

/**
 * @param $lookupHosts
 * @param $topic
 * @param string $channel
 * @param string $authSecret
 * @throws ClientException
 * @throws \NsqClient\lib\exception\LookupException
 */
function pub()
{
    $client = \NsqClient\lib\helper\HttpClient::getInstance('47.106.161.166:4151', 'dn.app.nsq.team');

    $d = [
        'action' => 1,
        'data'   => [
            'iTeamId' => 21,
            'userIds' => [87],
            'opType'  => 'addFriend'
        ]
    ];
    while (1) {
        $d['key'] = uniqid();
        $r = $client->pub($d);
        var_dump($r);
        sleep(1);
    }
}

pub();

######################

function sub()
{
    $lookupHost = '127.0.0.1:4160';
    $topic = $channel = 'test';
    // 重复排队10次，每次50秒延时下发
    $reQueue = new \NsqClient\lib\requeue\Requeue(10, 50);
    $client = new Client(
        $topic,
        $channel,
        '',
        function (\NsqClient\lib\message\Message &$message) {
            echo "收到消息:{$message->getId()}\n";
            $message->finish();
        },
        true,
        null,
        $reQueue,
        [
            'heartbeat_interval' => 1000//1秒的心跳间隔
        ]
    );
    // 开启的工作进程数
    $workNum = 2;
    (new NsqClient())->init($client, $lookupHost, $workNum);
}