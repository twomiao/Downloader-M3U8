<?php
declare(strict_types=1);

namespace Downloader\Runner;

/**
 * Class HttpRequest
 * @package Downloader\Runner
 */
class HttpRequest
{
    private string $url;
    private $curl;
    private array $options;
    private static $allowMethod = ['GET', 'POST', 'PUT', 'DELETE'];

    public function __construct(string $url, string $method, array $options = [])
    {
        // 初始化
        $this->url = $url;
        $this->options = $options;
        $this->options['user_agent'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.131 Safari/537.36';
        $this->options['request_method'] = strtoupper($method);
        $this->options['connect_timeout'] = $options['connect_timeout'] ?? 30;
        $this->options['timeout'] = $options['timeout'] ?? 30;
        $this->options['CURLOPT_SSL_VERIFYPEER'] = $options['CURLOPT_SSL_VERIFYPEER'] ?? false;
        $this->options['CURLOPT_SSL_VERIFYHOST'] = $options['CURLOPT_SSL_VERIFYHOST'] ?? false;
        $this->options['reties'] = $options['reties'] ?? 6;     // 重试次数
        $this->options['retry_sleep'] = $options['retry_sleep'] ?? 3;     // 每次失败，等待时间
        $this->options['CURLOPT_HEADER'] = $options['CURLOPT_HEADER'] ?? false;     // 该选项非常重要,如果不为 true, 只会获得响应的正文
        $this->options['CURLOPT_NOBODY'] = $options['CURLOPT_NOBODY'] ?? true;     // 是否不需要响应的正文,为了节省带宽及时间,在只需要响应头的情况下可以不要正文
        $this->options['header'] = $this->options['header'] ?? [];

        if (!\in_array($this->options['request_method'], self::$allowMethod, true)) {
            throw new \InvalidArgumentException('Allowed request methods: GET, POST, DELETE, PUT.');
        }
        $this->curl = \curl_init();
    }

    public function withHeader($header)
    {
        $this->options['header'] = $header;
        return $this;
    }

    public function withData($data)
    {
        $this->options['data'] = $data;
        return $this;
    }

    /**
     * @throws HttpResponseException
     */
    public function send()
    {
        $request_header = $this->options['header'];
        /**
         * [ CURLOPT_URL=> $url,CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_SSL_VERIFYHOST=>'xxx', ....]
         */
        \curl_setopt($this->curl, CURLOPT_URL, $this->url);
        \curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, $this->options['CURLOPT_SSL_VERIFYPEER']);
        \curl_setopt($this->curl, CURLOPT_SSL_VERIFYHOST, $this->options['CURLOPT_SSL_VERIFYHOST']);
        switch ($this->options['request_method']) {
            case 'POST':
                \curl_setopt($this->curl, CURLOPT_POST, true);
                \curl_setopt($this->curl, CURLOPT_POSTFIELDS, $this->options['data']);
                break;
            case 'GET':
                \curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, 'GET');
                break;
        }
        if ($request_header) {
            \curl_setopt($this->curl, CURLOPT_HTTPHEADER, $request_header);
        }

        \curl_setopt($this->curl, CURLOPT_HEADER, $this->options['CURLOPT_HEADER']);
        \curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($this->curl, CURLOPT_HEADER, true);
        \curl_setopt($this->curl, CURLOPT_TIMEOUT, $this->options['timeout']);
        \curl_setopt($this->curl, CURLOPT_CONNECTTIMEOUT, $this->options['connect_timeout']);
        \curl_setopt($this->curl, CURLOPT_USERAGENT, $this->options['user_agent']);
        return $this->execCurl();
    }

    /**
     * @throws HttpResponseException
     */
    protected function execCurl()
    {
        $max_value = (int)$this->options['reties'];
        $time = (int)$this->options['retry_sleep'];

        $resp_data = false;
        for ($reties = 1; $reties <= $max_value; $reties++) {

            $resp_data = \curl_exec($this->curl);
            if ($resp_data !== false) {
                break;
            }
            \Swoole\Coroutine::sleep($time);
        }

        if ($resp_data === false) {
            throw new HttpResponseException(\curl_error($this->curl), \curl_errno($this->curl));
        }
        $resp_header = \curl_getinfo($this->curl);

        return $this->resp($resp_header, $resp_data, $this->options);
    }

    protected function resp(array $resp_header, string $resp, array $options)
    {
        return new class($resp_header, $resp, $options)
        {
            private array $resp_header;
            private string $resp_data;
            private array $options;

            public function __construct($resp_header, $resp_data, $options)
            {
                $this->resp_header = $resp_header;
                $this->resp_data = $resp_data;
                $this->options = $options;

                // 开启header
                $openHeader = $this->options['CURLOPT_HEADER'];
                if ($openHeader) {
                    // header+bpdy
                    if ($this->options['CURLOPT_NOBODY'] === false) {
                        [$resp_headers, $this->resp_data] = explode("\r\n\r\n", $resp_data, 2);
                    } else {
                        // no body
                        $resp_headers = $resp_data;
                        $this->resp_data = '';
                    }
                    $this->resp_header = static::parserRespHeader($resp_headers);
                }
            }

            private static function parserRespHeader(string $resp_headers)
            {
                $headers = explode("\r\n", $resp_headers);

                $header_arr = [];
                foreach ($headers as $idx => $header) {
                    if (empty($header)) {
                        continue;
                    }

                    // HTTP 1.1 200 OK
                    if (!$idx) {
                        // status_code = 200,
                        //status_msg = ok
                        //http_protocol = 1.1
                        [$protocol, $protocol_version, $status_code, $status_msg] =
                            explode(' ', str_replace('/', ' ', $header));

                        $header = sprintf(
                            "http_protocol=%s&protocol_version=%s&status_code=%s&status_msg=%s",
                            $protocol, $protocol_version, $status_code, $status_msg
                        );
                    }

                    $header = str_replace([": ", '"'], ["=", ""], $header);

                    $header_arr[] = strtolower($header);
                }

                parse_str(implode('&', $header_arr), $header_arr);
                return $header_arr;
            }

            public function getData()
            {
                return $this->resp_data;
            }

            public function getHeaders(string $name = '')
            {
                if ($name) {
                    return $this->resp_header[$name];
                }
                return $this->resp_header;
            }
        };
    }

    public function __destruct()
    {
        if (\is_resource($this->curl)) {
            \curl_close($this->curl);
        }
    }
}