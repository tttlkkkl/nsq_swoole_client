<?php
/**
 * 生产者简易http客户端
 *
 * Created by li hua.
 * User: m
 * Date: 2019/2/27
 * Time: 21:25
 */

namespace NsqClient\lib\helper;


class HttpClient
{
    /**
     * 单消息推送uri
     */
    const PUB_URI = '/pub';
    /**
     * 批量消息推送uri
     */
    const MPUB_URI = '/mpub';
    /**
     * 话题
     *
     * @var string
     */
    protected $topic;
    /**
     * http 地址
     *
     * @var string
     */
    protected $host;

    protected static $obj;

    public function __construct($host = '', $topic = '')
    {
        $this->topic = $topic;
        $this->host = $host;
    }

    /**
     * @param string $host
     * @param string $topic
     * @return HttpClient
     */
    public function getInstance($host = '', $topic = '')
    {
        $key = $host . $topic;
        if (!isset(self::$obj[$key])) {
            self::$obj[$key] = new self($host, $topic);
        }
        return self::$obj[$key];
    }

    public function setTopic($topic)
    {
        $this->topic = $topic;
    }

    public function setHost($host)
    {
        $this->host = $host;
    }

    /**
     * 多消息推送
     *
     * @param array $data
     * @return bool
     * @throws \Exception
     */
    public function mPub(array $data)
    {
        if (empty($data)) {
            return false;
        }
        foreach ($data as $k => $v) {
            $data[$k] = json_encode($v);
        }
        $result = Http::post($this->getMPubUrl(), implode("\n", $data))->getBody();
        if (strtolower($result) == 'ok') {
            return true;
        }
        return false;
    }

    /**
     * 单消息推送
     *
     * @param array $data
     * @param string $defer 延时消息如 1ms 1s 1m 1h 1d 或 Y-m-d h:i:s
     * @return bool
     * @throws \Exception
     */
    public function pub(array $data, $defer = '')
    {
        if (empty($data)) {
            return false;
        }
        $result = Http::post($this->getPubUrl($defer), json_encode($data, JSON_UNESCAPED_UNICODE))->getBody();
        if (strtolower($result) == 'ok') {
            return true;
        }
        return false;
    }

    /**
     * 延时时间解析 返回 ms
     *
     * @param string $defer
     * @return int
     */
    private function parseDefer($defer)
    {
        if (self::isDateTime($defer)) {
            $t = strtotime($defer);
            if ($t < time()) {
                return 0;
            }
            return abs(time() - $t) * 1000;
        }
        if (!preg_match('/^(\d+)(ms|s|m|h|d)$/', $defer, $p)) {
            return 0;
        }
        $t = isset($p[1]) ? intval($p[1]) : 0;
        $u = isset($p[2]) ? $p[2] : '';
        switch ($u) {
            case 'ms':
                return $t;
                break;
            case 's':
                return $t * 1000;
                break;
            case 'm':
                return $t * 60 * 1000;
                break;
            case 'h':
                return $t * 3600 * 1000;
                break;
            case 'd':
                return $t * 24 * 3600 * 1000;
                break;
        }
        return 0;
    }

    private function getPubUrl($defer)
    {
        $deferTime = 0;
        if (!empty($defer)) {
            $deferTime = $this->parseDefer($defer);
        }
        return sprintf('%s%s?topic=%s&defer=%s', $this->host, self::PUB_URI, $this->topic, $deferTime);
    }

    private function getMPubUrl()
    {
        return sprintf('%s%s?topic=', $this->host, self::MPUB_URI, $this->topic);
    }

    /**
     * 检查是否是日期
     *
     * @param $date
     * @param string $format
     * @return bool
     */
    public static function isDateTime($date, $format = 'Y-m-d H:i:s')
    {
        $d = \DateTime::createFromFormat($format, $date);

        return $d && $d->format($format) == $date;
    }
}