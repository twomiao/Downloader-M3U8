<?php
namespace Downloader\Runner;

use Downloader\Runner\Contracts\HttpRequestInterface;

class HttpClient implements HttpRequestInterface
{
    private string $url;
    private   $ch;
    private int $timeout = 30;

    public function __construct(string $url, int $timeout = 30)
    {
        $this->url = $url;
        $this->ch = curl_init(); // create cURL handle (ch)
        if (! $this->ch ) {
            throw new \RuntimeException("Couldn't initialize a cURL handle");
        }
        $this->timeout = $timeout;
    }

    public function send(array $data = [], $method = 'GET') : Response
    {
        $header = [];
        $options = [
            CURLOPT_URL=>$this->url,
            CURLOPT_HEADER=>0,
            CURLOPT_FOLLOWLOCATION=>1,
            CURLOPT_RETURNTRANSFER=>1,
            CURLOPT_TIMEOUT=>$this->timeout
        ];
        if ($data) {
            $options[CURLOPT_POSTFIELDS] = $data;
        }
        curl_setopt_array($this->ch, $options);
        $ret = curl_exec( $this->ch);

        try {
            if (empty($ret)) {
                // some kind of an error happened
                throw new \Exception(  \curl_error( $this->ch) );
            }
            $header = curl_getinfo( $this->ch);
            if (empty($header['http_code'])) {
                throw new \Exception("No HTTP code was returned");
            }
        } finally {
            if(is_resource($this->ch)) {
                \curl_close( $this->ch); // close cURL handler
            }
        }
        return new Response($header, $ret);
    }
}