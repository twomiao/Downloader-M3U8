<?php

use Downloader\Miaotwo\Logger;
use Downloader\Miaotwo\MovieParser;
use Downloader\Parsers\M1905;
use Downloader\Miaotwo\Downloader;
use Downloader\Miaotwo\Utils;

require_once __DIR__ . '/../../vendor/autoload.php';

$urls = [
    // 添加更多M3U8地址信息 .....
    "202010121039" => "https://m3u8i.vodfile.m1905.com/202010161318/3fd3a07ab4597e0a451fb5051ab2322e/movie/2020/04/15/m20200415OSAAEGK4NBKO8KUC/BE09269EE68AD9C5B153840F2.m3u8"
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
