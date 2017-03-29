<?php

/**
 * 同步推送客户端示例
 *
 * @datetime: 2017/3/29 15:11
 * @author: lihs
 * @copyright: ec
 */
require('../Bootsrap.php');
use lib\NsqClient;

$NsqClient = new NsqClient();
$client = $NsqClient->getSynClient('127.0.0.1', 4150, 'nsq_common',md5('nsns'));
while (1) {
    var_dump($client->pub('message'));
    //var_dump($client->mPub(array_fill(0,100,'message')));
    //usleep(2000);
    sleep(1);
}
