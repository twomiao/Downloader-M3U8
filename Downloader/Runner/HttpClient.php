<?php
declare(strict_types=1);

namespace Downloader\Runner;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class HttpClient
{
    /**
     * 请求方式可以扩展
     */
    const GET_METHOD = 'get';
    const POST_METHOD = 'post';

    protected $responseCode = 0;

    protected $options = [
        'wait' => 1,
        'request_method' => self::GET_METHOD,
        'timeout' => 30,
        'connect_timeout' => 15,
        'retries' => 10,
        'headers' => [],
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/63.0.3239.132 Safari/537.36'
    ];

    /**
     * @var array $postData
     */
    protected $postData = [];

    /**
     * @var $result
     */
    protected $result;

    /**
     * @var ContainerInterface
     */
    protected $container;

    protected $curl;

    public function __construct($options = [])
    {
        $this->options = array_merge($this->options, $options);
    }

    public function setContainer(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getBodySize()
    {
        if ($this->isSucceed()) {
            return strlen($this->result);
        }
        return 0;
    }

    public function getBody()
    {
        return $this->result;
    }

    public function isSucceed()
    {
        return $this->result !== false && $this->responseCode === 200;
    }

    public function getResponseCode()
    {
        return $this->responseCode;
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
        $this->curl = curl_init();
        curl_setopt($this->curl, CURLOPT_URL, $url);
        curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($this->curl, CURLOPT_HTTPHEADER, $this->options['headers']);
        $this->curlRequestMethod($this->curl);
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curl, CURLOPT_TIMEOUT, $this->options['timeout']);
        curl_setopt($this->curl, CURLOPT_CONNECTTIMEOUT, $this->options['connect_timeout']);
        curl_setopt($this->curl, CURLOPT_USERAGENT, $this->options['user_agent']);

        $reties = 0;
        while ($reties < $this->options['retries'] && !$this->result = curl_exec($this->curl)) {
            $reties++;
            sleep(1);
            if ($reties > 6) {
                $this->container->get(LoggerInterface::class)->info("Retries ($reties) sleep(1), failed request: {$url}");
            }
        }
        $this->responseCode = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
        // closed curl
        $curl_errno = 0;
        if ($this->result === false || $curl_errno = curl_errno($this->curl) !== 0 ) {
            $curl_error = curl_error($this->curl);
            throw new RetryRequestException("Failed url {$url}, Curl error: {$curl_error}, Curl code:{$curl_errno}.");
        }
        return $this;
    }

    public function closed()
    {
        if ($this->curl)
        {
            return curl_close($this->curl);
        }
        return false;
    }
}