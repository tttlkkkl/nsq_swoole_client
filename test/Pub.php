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
$client = $NsqClient->getSynClient('127.0.0.1', 4150, 'nsq_common', 'tGzv3JOkF0XG5Qx2TlKWIA');
while (1) {
    var_dump($client->pub('message'));
    var_dump($client->mPub(array_fill(0,1000,'message')));
    //usleep(2000);
    sleep(1);
}

