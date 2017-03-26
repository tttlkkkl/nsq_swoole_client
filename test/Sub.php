<?php
/**
 *
 * Date: 17-3-25
 * Time: 下午8:31
 * author :李华 yehong0000@163.com
 */
require('../Bootsrap.php');
use lib\client\Client;
use lib\NsqClient;
use lib\lookup\Lookup;
$Client = new Client('nsq_common','web_member');
$NsqClient = new NsqClient();
$lookup=new Lookup();
$hosts=$lookup->lookupHosts('nsq_common');
$NsqClient->init($Client,$hosts[0]);