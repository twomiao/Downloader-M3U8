<?php

namespace Downloader\Parsers;

use Downloader\Miaotwo\MovieParserInterface;

/**
 * 案例
 * Class BaoYun29
 * @package Downloader\Parsers
 */
class BaoYun29 implements MovieParserInterface
{
    public function parsedTsUrl($m3u8Url, $ts): string
    {
        // 1. M3U8文件本身，是一个完整可用地址
        // 2. 因此无需处理，直接返回即可
        return $ts;
    }
}