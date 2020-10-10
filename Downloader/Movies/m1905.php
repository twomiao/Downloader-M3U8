<?php

use Downloader\Miaotwo\Logger;
use Downloader\Miaotwo\MovieParser;
use Downloader\Parsers\M1905;
use Downloader\Miaotwo\Downloader;
use Downloader\Miaotwo\Utils;

require_once __DIR__ . '/../../vendor/autoload.php';

$urls = [
    // 添加更多M3U8地址信息 .....
    "https://m3u8i.vodfile.m1905.com/202010110619/88d6e6252f5887de7f14b7f928b1fe6d/movie/2015/01/07/m20150107OSHOA61YXCI9UYYJ/m20150107OSHOA61YXCI9UYYJ-561k.m3u8",
    "https://m3u8i.vodfile.m1905.com/202010110618/49b5604740f90d852e759d871d27d0be/movie/1206/1206285AA18C08D8615A04-564k.m3u8",
//    "https://m3u8i.vodfile.m1905.com/202010110620/c006649b8bbf91d7df55c9ff904d44af/movie/2014/07/10/m20140710LP24JW3CN8IB86H6/m20140710LP24JW3CN8IB86H6-534k.m3u8"
];

// 保存到本地电脑指定目录
$local = '/mnt/c/Users/twomiao/desktop/download';
// 开始时间
$run_at = time();

foreach ($urls as $url) {
    $movieParser = new MovieParser($url, new M1905());
    (new Downloader($movieParser, $local))
        ->maxConcurrent(40)
        ->run();
}
// 结束时间
$end_at = Utils::timeCost($run_at);
Logger::create()->info("下载完成用时：{$end_at}", '[ Done ] ');
