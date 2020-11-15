<?php
namespace Downloader\Parsers;

use Downloader\Runner\MovieParser;

class YouKu extends MovieParser
{
    /**
     * @param $m3u8Url string 视频文件信息
     * @param $movieTs  string ts文件名称
     * @return string  返回完整ts视频地址
     */
    protected function parsedTsUrl(string $m3u8Url, string $movieTs): string
    {
        return str_replace("index.m3u8", $movieTs, $m3u8Url);
    }
}