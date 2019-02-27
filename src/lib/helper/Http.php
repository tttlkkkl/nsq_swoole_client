<?php
/**
 * http 请求的简易封装
 *
 * Created by li hua.
 * User: m
 * Date: 2019/2/27
 * Time: 21:11
 */

namespace NsqClient\lib\helper;


class Http
{
    protected static $timeout = 60;
    /**
     * @param $url
     * @param $params
     * @param array $header
     * @return HttpResponse
     * @throws \Exception
     */
    static public function post($url, $params, $header = [])
    {
        $data = array();
        if (is_array($params)) {
            foreach ($params as $k => $v) {
                $data[] = $k . '=' . urlencode($v);
            }
            $data = join('&', $data);
        } else {
            $data = $params;
        }
        return self::execute($url, $data, $header, 1);
    }

    /**
     * @param $url
     * @param $params
     * @param array $header
     * @return HttpResponse
     * @throws \Exception
     */
    static public function get($url, $params, $header = [])
    {
        $index = strpos($url, '?');
        if ($index !== false && $params) {
            $url .= '&' . http_build_query($params, '&');
        } elseif ($params) {
            $url .= '?' . http_build_query($params, '&');
        }
        return self::execute($url, NULL, $header, 2);
    }

    /**
     * @param $url
     * @param $path
     * @param $name
     * @param array $header
     * @return HttpResponse
     * @throws \Exception
     */
    static public function file($url, $path, $name, $header = [])
    {
        $data = array(
            'file'     => '@' . $path,
            'filename' => $name,
        );
        return self::execute($url, $data, $header, 1);
    }

    /**
     * @param $url
     * @param $params
     * @param array $header
     * @return HttpResponse
     * @throws \Exception
     */
    static public function put($url, $params, $header = [])
    {
        return self::execute($url, $params, $header, 3);
    }

    /**
     * @param $url
     * @param $params
     * @param array $header
     * @return HttpResponse
     * @throws \Exception
     */
    static public function delete($url, $params, $header = [])
    {
        return self::execute($url, $params, $header, 4);
    }

    /**
     * 设置超时时间
     *
     * @param $second
     */
    static public function setTimeout($second)
    {
        self::$timeout = $second;
    }

    /**
     * @param $url
     * @param $data
     * @param $header
     * @param int $type
     * @return HttpResponse
     * @throws \Exception
     */
    static private function execute($url, $data, $header, $type = 1)
    {
        $cu = curl_init();#开始curl会话
        if (!function_exists("curl_init") &&
            !function_exists("curl_setopt") &&
            !function_exists("curl_exec") &&
            !function_exists("curl_close")
        ) {
            throw new \Exception('curl模块错误！', -8201);
        }

        if (stripos($url, 'https://') !== false) {
            curl_setopt($cu, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($cu, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($cu, CURLOPT_SSL_VERIFYPEER, false);    // https请求 不验证证书和hosts
            curl_setopt($cu, CURLOPT_SSLVERSION, 1); //CURL_SSLVERSION_TLSv1
        }
        switch ($type) {
            //poet
            case 1:
                curl_setopt($cu, CURLOPT_POST, 1);
                curl_setopt($cu, CURLOPT_POSTFIELDS, $data);
                break;
            //get
            case 2:
                curl_setopt($cu, CURLOPT_POST, 0);
                break;
            //put
            case 3:
                $header[] = 'X-HTTP-Method-Override: PUT';
                curl_setopt($cu, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($cu, CURLOPT_POSTFIELDS, $data);
                break;
            //delete
            case 4:
                $header[] = 'X-HTTP-Method-Override:  DELETE';
                curl_setopt($cu, CURLOPT_CUSTOMREQUEST, 'DELETE');
                curl_setopt($cu, CURLOPT_POSTFIELDS, $data);
                break;
        }
        if (!empty($header)) {
            curl_setopt($cu, CURLOPT_HTTPHEADER, $header);
        }
        curl_setopt($cu, CURLOPT_URL, $url);
        curl_setopt($cu, CURLOPT_TIMEOUT, self::$timeout);
        curl_setopt($cu, CURLOPT_HEADER, true);//http头
        curl_setopt($cu, CURLOPT_RETURNTRANSFER, true);#内容做为变量存储

        $tmp = curl_exec($cu);
        if ($err = curl_error($cu)) {
            curl_close($cu);
            throw new \Exception($err . $tmp, 500);
        }
        $headerSize = curl_getinfo($cu, CURLINFO_HEADER_SIZE);
        $rsp = new HttpResponse(substr($tmp, 0, $headerSize), substr($tmp, $headerSize), intval(curl_getinfo($cu, CURLINFO_HTTP_CODE)));
        curl_close($cu);
        return $rsp;
    }
}