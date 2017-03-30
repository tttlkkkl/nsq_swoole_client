<?php
/**
 *
 * Date: 17-3-25
 * Time: 下午8:31
 * author :李华 yehong0000@163.com
 */
require('../Bootsrap.php');
use lib\NsqClient;
$NsqClient = new NsqClient();
$NsqClient->sub(['127.0.0.1:4161'],'nsq_common','web_member','auth');