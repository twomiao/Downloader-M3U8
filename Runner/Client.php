<?php
namespace Downloader\Runner;

use Exception;
use RuntimeException;
use Swoole\Coroutine\Http\Client as SwooleClient;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

class Client
{
    protected ?SwooleClient $client = null;
    protected ?string $url = null;

    public function __construct(string $url)
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new RuntimeException("This is an invalid address{ {$url} }.");
        }

        $this->url = $url;
        $configFile = Container::make('config');

        $https = $this->withUrlHttps($url);
        $port  = $this->getServerPort();
        $host  = parse_url($url, PHP_URL_HOST);
        $this->client = new SwooleClient($host, $port, $https);

        // http 参数配置
        $this->client->set($configFile->http_set);

    }

    public function downloadFile(FileSlice $fileSlice) : int {
        /**
         * @var $reponse Response
         */
        $respnose = $this->send("get");
        if (($code = $respnose->getStatusCode()) !== 200)
        {
            throw new \RuntimeException("Download file ( $this->url ) failed status code {$code}.");
        }
        // info($this->url . ' ' . $code);
        $data = $respnose->getBody();
        // 如果加密解密
        if($fileSlice->file->isEncrypted())
        {
            $data = $fileSlice->file->decrypt($data);
        }
        
        return $fileSlice->save($data);
    }

    protected function getServerPort() : int {
        $port = parse_url($this->url, PHP_URL_PORT);
        if (is_null($port))
        {
            return $this->withUrlHttps($this->url) ? 443 : 80;
        }
        return $port;
    }

    protected function withUrlHttps(string $url) : bool {
        return match(parse_url($url, PHP_URL_SCHEME)) {
            "https" => true,
            default => false,
        };
    }

    public function send(string $method, array $data = []) : Response
    {
        return match($method) {
            "GET","get" => $this->getRequest(),
            "POST", "post" => $this->postRequest()
        };
    }

    protected function getRequest() : Response {
       $count = 0;
       RETRY_REQUEST:
        $this->client->get(parse_url($this->url, PHP_URL_PATH));
    
        $statusCode = $this->client->getStatusCode();
        if ($statusCode === false) {
            throw new ClientException($this->client);
        }
        if ( $statusCode < 0) {
            // 	请求超时，服务器未在规定的 timeout 时间内返回 response
            if ($statusCode === -2 || $statusCode === -1)
            {
                $retry = Container::make('config')->http_set['retry_num'] ?? 0;
                if ($count++ <= $retry) {
                    // 请求重试
                    goto RETRY_REQUEST;
                } 
            }
            throw new ClientException($this->client);
        }
        return new Response($statusCode, $this->client->getHeaders(), $this->client->getBody());
    }

    protected function postRequest() {
        throw new \Exception("Post request not yet supported.");
    }

    public function __destruct()
    {
        if (!is_null($this->client) && $this->client instanceof SwooleClient)
        {
            $this->client->close();
        }
    }
}