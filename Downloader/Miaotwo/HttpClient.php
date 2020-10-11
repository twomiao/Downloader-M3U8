<?php

namespace Downloader\Miaotwo;

class HttpClient
{
    protected $errorMsg;

    protected $statusCode;

    protected $errorCode = 0;

    const GET_METHOD = 'get';

    const POST_METHOD = 'post';

    private $postData = [];

    private $resp;

    protected $options = [
        'request_method' => self::GET_METHOD,
        'timeout' => 30,
        'connect_timeout' => 60,
        'retries' => 3,
        'headers' => 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/63.0.3239.132 Safari/537.36'
    ];

    public function __construct($options = [])
    {
        $this->options = array_merge($this->options, $options);
    }


    public function getBodySize()
    {
        if ($this->isResponseOk()) {
            return strlen($this->resp);
        }
        return 0;
    }

    public function getBody()
    {
        return $this->resp;
    }

    public function isResponseOk()
    {
        return $this->statusCode === 200;
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }

    public function get()
    {
        $this->options['request_method'] = self::GET_METHOD;
        return $this;
    }

    public function post(array $data = [])
    {
        $this->postData = $data;
        $this->options['request_method'] = self::POST_METHOD;
        return $this;
    }

    public function setTimeout($timeout)
    {
        $this->options['timeout'] = ($timeout) < 1 ? 3 : $timeout;
        return $this;
    }

    public function setConnectTimeout($connectTimeout)
    {
        $this->options['connect_timeout'] = ($connectTimeout) < 1 ? 3 : $connectTimeout;
        return $this;
    }

    protected function curlRequestMethod($curl)
    {
        $requestMethod = $this->options['request_method'];

        switch ($requestMethod) {
            case self::POST_METHOD:
                curl_setopt($curl, CURLOPT_POST, 1);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $this->postData);
                break;
            case self::GET_METHOD:
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'GET');
                break;
        }
    }

    public function request($url)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        $this->curlRequestMethod($curl);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->options['timeout']);
        curl_setopt($curl, CURLOPT_HEADER, $this->options['headers']);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->options['connect_timeout']);
        $this->resp = curl_exec($curl);
        $this->statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        $reties = 0;
        while (
            $this->resp === false &&
            $reties < $this->options['retries'] &&
            $this->statusCode !== 200
        ) {
            $reties++;
            usleep(500000);
            $this->resp = curl_exec($curl);
            var_dump("retry={$reties},statusCode={$this->statusCode}");
            /* if ($downloadedSize < 1024) {
                  Logger::create()->warn("重试({$reries})次数, 网络地址：{$tsUrl}", "[ Retry ] ");
              }*/
        }

        if (is_bool($this->resp) && $this->resp === false) {
            $this->errorMsg = curl_error($curl);
            $this->errorCode = curl_errno($curl);
            //  Logger::create()->error("{$curlError}, resp code:{$respCode} url addr：{$url}", "[ Error ] ");
        }
        curl_close($curl);
        return $this;
    }
}