<?php

use Downloader\Miaotwo\Logger;
use Downloader\Miaotwo\MovieParser;
use Downloader\Parsers\M1905;
use Downloader\Miaotwo\Downloader;
use Downloader\Miaotwo\Utils;

require_once __DIR__ . '/../../vendor/autoload.php';

$urls = [
    // 添加更多M3U8地址信息 .....
    "202010121039" => "https://m3u8i.vodfile.m1905.com/202010121039/89ed821bfa865c60b2100cecee4b4c76/movie/2019/11/01/m201911013H75YYU0X8TGQ5XV/31F7C75013716C57F444253B3.m3u8"
    ,"202010121125" => "https://m3u8i.vodfile.m1905.com/202010121125/d139274efe5d9ee1b1ffddd0ddb1ac57/movie/1206/1206285AA18C08D8615A04-564k.m3u8"
    ,"202010121330" => "https://m3u8i.vodfile.m1905.com/202010121330/59d8030f8085c58d71421c2892d07feb/movie/2016/08/26/m201608269L0YEKI92C48CWV1/1C0796B777A96CF92D5192874.m3u8"
];

// 保存到本地电脑指定目录
$local = '/mnt/c/Users/twomiao/desktop/download';
// 开始时间
$run_at = time();

// 1:23:26
// 1:42:17
// 2:08:00

foreach ($urls as $filename => $url) {
    $filename = $local . "/{$filename}/{$filename}.mp4";
    if (!is_file($filename)) {
        $movieParser = new MovieParser($url, new M1905());
        (new Downloader($movieParser, $filename))
            ->maxConcurrent(40)
            ->run();
    }

}
// 结束时间
$end_at = Utils::timeCost($run_at);
Logger::create()->info("下载完成用时：{$end_at}", '[ Done ] ');
