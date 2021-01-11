<?php
namespace Downloader\Runner\Middleware;

use Downloader\Runner\Middleware\Data\Mu38Data;
use League\Pipeline\StageInterface;

/**
 * 解密RSA视频
 * Class RsaDecryptMiddleware
 * @package Downloader\Runner\Middleware
 */
class RsaDecryptMiddleware implements StageInterface
{
    /**
     * Process the payload.
     *
     * @param Mu38Data $data
     *
     * @return mixed
     */
    public function __invoke($data)
    {
        return $data;
    }
}