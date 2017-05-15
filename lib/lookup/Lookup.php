<?php

/**
 * 服务发现
 * Date: 17-3-25
 * Time: 下午10:56
 * author :李华 yehong0000@163.com
 */
namespace lib\lookup;

use lib\exception\LookupException;
use lib\log\Log;

class Lookup
{
    /**
     * lookup 服务地址列表  host:port
     * @var array
     */
    private $hosts;

    /**
     * curl 连接超时时间
     * @var float|int
     */
    private $connectionTimeout;

    /**
     * 等待响应时间
     * @var
     */
    private $resultesponseTimeout;

    /**
     * 日志打印
     * @var Log
     */
    private $Log;

    /**
     * Lookup constructor.
     *
     * @param array $hosts
     * @param int $connectionTimeout
     * @param int $resultesponseTimeout
     */
    public function __construct($hosts = NULL, $connectionTimeout = 1, $resultesponseTimeout = 2)
    {
        if ($hosts === NULL) {
            $this->hosts = ['localhost:4161'];
        } elseif (is_array($hosts)) {
            $this->hosts = $hosts;
        } else {
            $this->hosts = explode(',', $hosts);
        }
        $this->connectionTimeout = $connectionTimeout;
        $this->responseTimeout = $resultesponseTimeout;
        $this->Log = new Log();
    }

    /**
     * 服务发现
     *
     * @param $topic
     *
     * @return array
     */
    public function lookupHosts($topic)
    {
        $lookupHosts = [];
        $topicChannel = [];
        foreach ($this->hosts as $host) {
            if (strpos($host, ':') === FALSE) {
                $host .= ':4161';
            }

            $url = "http://{$host}/lookup?topic=" . urlencode($topic);
            $ch = curl_init($url);
            $options = array(
                CURLOPT_RETURNTRANSFER => TRUE,
                CURLOPT_HEADER         => FALSE,
                CURLOPT_FOLLOWLOCATION => FALSE,
                CURLOPT_ENCODING       => '',
                CURLOPT_USERAGENT      => 'nsq_swoole_client',
                CURLOPT_CONNECTTIMEOUT => $this->connectionTimeout,
                CURLOPT_TIMEOUT        => $this->responseTimeout,
                CURLOPT_FAILONERROR    => TRUE
            );
            curl_setopt_array($ch, $options);
            $resultString = curl_exec($ch);
            echo $resultString;
            if (!curl_error($ch) && curl_getinfo($ch, CURLINFO_HTTP_CODE) == '200') {
                $result = json_decode($resultString, TRUE);
                if(isset($result['data']['producers'])){
                    //0.3.8
                    $producers=$result['data']['producers'];
                }elseif(isset($result['producers'])){
                    //1.0.0
                    $producers=$result['producers'];
                }
                foreach ($producers as $prod) {
                    $address = $prod['broadcast_address'];
                    $h = "{$address}:{$prod['tcp_port']}";
                    if (!in_array($h, $lookupHosts)) {
                        $lookupHosts[] = $h;
                        $topicChannel[$h]['channels'] = isset($result['data']['channels']) ? $result['data']['channels'] : [];
                    }
                }
                curl_close($ch);
            } else {
                $err = curl_error($ch);
                $this->Log->error($err . $resultString);
                curl_close($ch);
                throw new LookupException($err, -1);
            }
        }
        return [
            'lookupHosts'  => $lookupHosts,
            'topicChannel' => $topicChannel
        ];
    }
}