<?php
/**
 * 编码示例
 *
 * Date: 17-3-25
 * Time: 下午8:31
 * author :李华 yehong0000@163.com
 */

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
function sub( $lookupHosts, $topic, $channel = '', $authSecret = '' )
{
    $Lookup = new Lookup($lookupHosts);
    $nsqdList = $Lookup->lookupHosts($topic);
    if ( !$nsqdList || !isset($nsqdList['lookupHosts']) || !$nsqdList['lookupHosts'] || !is_array($nsqdList['lookupHosts']) ) {
        throw new ClientException('未发现可用服务');
    }
    $NsqClient = new NsqClient();
    foreach ( $nsqdList['lookupHosts'] as $host ) {
        if ( !$channel ) {
            $channel = isset($nsqdList['topicChannel'][ $host ][0]) ? $nsqdList['topicChannel'][ $host ][0] : 'nsq_swoole_client';
        }
        $Client = new Client($topic, $channel, $authSecret);
        $NsqClient->init($Client, $host);
    }
}

try {
    sub([ '127.0.0.1:4161' ], 'nsq_common', 'web_member', 'auth');
} catch ( Exception $e ) {
    exit($e->getMessage());
}
