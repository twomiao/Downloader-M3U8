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
        return $movieTs;
    }

    protected function getParsekey($data)
    {
        $data = parent::getParsekey($data);

        /**
         * array(2) {
         *["method"]=>
         *string(7) "AES-128"
         *["keyUri"]=>
         *string(7) "key.key"
         *}
         */
//        $keyUri = $data['keyUri'];
//        $data['keyUri'] =  "https://.......com/81820200424/GC0229379/1000kb/hls/{$keyUri}";

        return $data;
    }

}