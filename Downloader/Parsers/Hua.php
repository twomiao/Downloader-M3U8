<?php
namespace Downloader\Parsers;

use Downloader\Runner\MovieParser;

class Hua extends MovieParser
{

    /**
     * @param $m3u8Url string 视频文件信息
     * @param $movieTs  string ts文件名称
     * @return string  返回完整ts视频地址
     */
    protected function parsedTsUrl(string $m3u8Url, string $movieTs): string
    {
        return "https://m3u8i.vodfile.m1905.com/202011220309/972a4a041420ecca90901d33fa2086ee/movie/2017/06/15/m201706152917FI77DD7VW2PA/{$movieTs}";
    }
}