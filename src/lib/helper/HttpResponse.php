<?php
/**
 * http 请求结果
 *
 * Created by li hua.
 * User: m
 * Date: 2019/2/27
 * Time: 21:12
 */

namespace NsqClient\lib\helper;


class HttpResponse
{
    protected $header;
    protected $body;
    protected $httpStatus;

    public function __construct( $header,  $body,  $httpStatus)
    {
        $this->body = $body;
        $this->header = $header;
        $this->httpStatus = $httpStatus;
    }

    /**
     * @return string
     */
    public function getHeader()
    {
        return $this->header;
    }

    /**
     * @return string
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * @return int
     */
    public function getHttpStatus()
    {
        return $this->httpStatus;
    }
}