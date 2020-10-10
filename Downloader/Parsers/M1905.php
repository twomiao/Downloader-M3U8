<?php
namespace Downloader\Parsers;

use Downloader\Miaotwo\MovieParserInterface;

/**
 * 网站地址：https://www.1905.com/vod/?fr=homepc_menu_vod
 * 1905电影网解析规则
 * 特别说明：这是一个完整可运行的案例
 * Class M1905
 * @package Downloader\Parsers
 */
class M1905 implements MovieParserInterface
{
    /**
     * 返回一个完整的Ts视频片段地址
     * @param $m3u8Url M3U8地址
     * @param $movieTs 分片地址自行处理
     * @return string
     */
    public function parsedTsUrl($m3u8Url, $movieTs): string
    {
        // 由于地址不够完整,需要自行处理返回一个完整地址
        $length = strrpos($m3u8Url, '/') + 1;
        return substr($m3u8Url, 0, $length) . $movieTs;
    }
}