<?php
declare(strict_types=1);

namespace Downloader\Runner;

use Downloader\Runner\Contracts\HttpRequestInterface;

/**
 * Class HttpRequest
 * @package Downloader\Runner
 */
class HttpRequest implements HttpRequestInterface
{
    private array $options;

    public function __construct(array $options = [])
    {
        $this->options = $options;
        $this->options['USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.131 Safari/537.36';
        $this->options['CONNECT_TIMEOUT'] = $options['CONNECT_TIMEOUT'] ?? 30;
        $this->options['TIMEOUT'] = $options['TIMEOUT'] ?? 30;
        $this->options['CURLOPT_SSL_VERIFYPEER'] = $options['CURLOPT_SSL_VERIFYPEER'] ?? false;
        $this->options['CURLOPT_SSL_VERIFYHOST'] = $options['CURLOPT_SSL_VERIFYHOST'] ?? false;
        $this->options['RETIES'] = intval($options['RETIES'] ?? 6);     // 重试次数
        $this->options['RETRY_SLEEP'] = intval($options['RETRY_SLEEP'] ?? 3);     // 每次失败，等待时间
        $this->options['CURLOPT_HEADER'] = $options['CURLOPT_HEADER'] ?? false;     // 该选项非常重要,如果不为 true, 只会获得响应的正文
        $this->options['CURLOPT_NOBODY'] = $options['CURLOPT_NOBODY'] ?? true;     // 是否不需要响应的正文,为了节省带宽及时间,在只需要响应头的情况下可以不要正文
        $this->options['REQUEST_HEADERS'] = $this->options['REQUEST_HEADERS'] ?? [];
    }

    /**
     * @param $header
     * @return $this
     */
    public function withHeader($header)
    {
        $this->options['REQUEST_HEADERS'] = $header + $this->options['REQUEST_HEADERS'];
        return $this;
    }

    /**
     * @param string $url
     * @param array $data
     * @param string $method
     * @return Response|null
     * @throws HttpResponseException
     */
    public function send(string $url, array $data = [], $method = 'GET'): ?Response
    {
        $curl = \curl_init();
        $request_header = $this->options['REQUEST_HEADERS'];
        $reties = $this->options['RETIES'];
        $retrySleep = $this->options['RETRY_SLEEP'];
        /**
         * 可通过数组配置参数保存
         * [ CURLOPT_URL=> $url,CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_SSL_VERIFYHOST=>'xxx', ....]
         */
        \curl_setopt($curl, CURLOPT_URL, $url);
        \curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $this->options['CURLOPT_SSL_VERIFYPEER']);
        \curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, $this->options['CURLOPT_SSL_VERIFYHOST']);
        switch (strtoupper($method)) {
            case 'POST':
                \curl_setopt($curl, CURLOPT_POST, true);
                \curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
                break;
            case 'GET':
                \curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'GET');
                break;
            default:
                throw new \InvalidArgumentException('Allowed request methods: GET, POST.');
        }
        if ($request_header) {
            \curl_setopt($curl, CURLOPT_HTTPHEADER, $request_header);
        }

        \curl_setopt($curl, CURLOPT_HEADER, $this->options['CURLOPT_HEADER']);
        \curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($curl, CURLOPT_HEADER, true);
        \curl_setopt($curl, CURLOPT_TIMEOUT, $this->options['TIMEOUT']);
        \curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->options['CONNECT_TIMEOUT']);
        \curl_setopt($curl, CURLOPT_USERAGENT, $this->options['USER_AGENT']);

        $resp_data = false;
        for ($i = 1; $i <= $reties; $i++) {
            $resp_data = \curl_exec($curl);
            if ($resp_data !== false) {
                break;
            }
            \Swoole\Coroutine::sleep($retrySleep);
        }

        $response = null;

        try {
            if ($resp_data === false) {
                throw new HttpResponseException(\curl_error($curl), \curl_errno($curl));
            }
            $resp_header = \curl_getinfo($curl);

            $response = new Response($resp_header, $resp_data, $this->options);
        } finally {
            if (\is_resource($curl)) {
                \curl_close($curl);
            }
            return $response;
        }
    }
}