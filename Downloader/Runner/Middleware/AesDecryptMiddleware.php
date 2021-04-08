<?php

namespace Downloader\Runner\Middleware;

use Downloader\Runner\HttpClient;
use Downloader\Runner\Middleware\Data\Mu38Data;
use Downloader\Runner\RetryRequestException;
use League\Pipeline\StageInterface;

/**
 * 解密AES 视频
 * Class AesDecryptMiddleware
 * @package Downloader\Runner\Middleware
 */
class AesDecryptMiddleware implements StageInterface
{
    /**
     * @param Mu38Data $data
     * @return mixed
     */
    public function __invoke($data)
    {
        return $this->decrypt($data->getRawData());
    }

    protected function decrypt($data)
    {
        $password = "f8e85a27aba9bafa";
        $data = openssl_decrypt($data, 'aes-128-cbc', $password,OPENSSL_RAW_DATA);
        return $data;
    }
}
