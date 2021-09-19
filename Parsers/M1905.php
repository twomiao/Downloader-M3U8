<?php

namespace Downloader\Parsers;

use Downloader\Runner\FileException;
use Downloader\Runner\FileM3u8;
use Downloader\Runner\Parser;

class M1905 extends Parser
{
    protected static function tsUrl(string $m3u8FileUrl, string $partTsUrl): string
    {
        return dirname($m3u8FileUrl) . '/' . $partTsUrl;
    }

    protected static function fileName(string $m3u8FileUrl): string
    {
        return basename(dirname($m3u8FileUrl, 3));
    }

    static function decodeData(string $data): string
    {
        $data = \openssl_decrypt($data, 'AES-128-CBC', FileM3u8::$decryptKey, OPENSSL_RAW_DATA);
        if ($data === false) {
            throw new FileException(
                sprintf("尝试解密方式和秘钥 [%s] - [%s] 解密失败!", FileM3u8::$decryptMethod, FileM3u8::$decryptKey)
            );
        }
        return $data;
    }

    /**
     * 秘钥完整URL
     * @param string $m3u8FileUrl
     * @param string $keyUrl
     * @return string
     */
    protected static function getKeyUrl(string $m3u8FileUrl, string $keyUrl): string
    {
        return dirname($m3u8FileUrl) . '/' . $keyUrl;
    }
}