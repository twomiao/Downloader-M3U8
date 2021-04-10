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
//        $url = str_replace("m2014070882MYZ4QYL20IY6US-535k.m3u8", "", $m3u8Url);
//
//        return "{$url}{$movieTs}";
//        var_dump($movieTs);

        return 'https://m3u8i.vodfile.m1905.com/202104111303/5750d10b976913b603178623791ce8b2/movie/2019/11/01/m201911013H75YYU0X8TGQ5XV/'.$movieTs;
    }
}