<?php
declare(strict_types=1);
namespace Downloader\Runner;

use League\Container\Container;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class HttpClient
{
    /**
     * 请求方式可以扩展
     */
    const GET_METHOD  = 'get';
    const POST_METHOD = 'post';

    protected $errorMsg;

    protected $statusCode;

    protected $errorCode = 0;

    protected $options = [
        'wait'      => 1,
        'request_method' => self::GET_METHOD,
        'timeout' => 30,
        'connect_timeout' => 60,
        'retries' => 6,
        'headers' => 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/63.0.3239.132 Safari/537.36'
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

    public function __construct($options = [])
    {
        $this->options   = array_merge($this->options, $options);
    }

    public function setContianer(ContainerInterface $container)
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
        return $this->result !== false;
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
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->options['timeout']);
        curl_setopt($curl, CURLOPT_HEADER, $this->options['headers']);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->options['connect_timeout']);

        $reties = 0;

        while($reties < $this->options['retries'] && ! $this->result = curl_exec($curl))
        {
            $reties++;
            $wait = $this->options['wait'] * $reties;
            sleep($wait);
            $this->container->get(LoggerInterface::class)->info("Number of retries ($reties) sleep($wait), failed request: {$url}");
        }
        // statusCode
        $this->statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        // closed curl
        curl_close($curl);

        if($this->result === false)
        {
            throw new RetryRequestException("Failed url {$url}, Curl error: " . curl_error($curl), curl_errno($curl));
        }
        return $this;
    }
}