<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Downloader\Miaotwo\MovieParser;
use Downloader\Parsers\Xc0125;
use Downloader\Miaotwo\Downloader;

$urls = [
    // 添加多个M3U8视频地址，提醒：只能同一个域名下面
];

// 保存到已存在的目录
$local = '/mnt/c/Users/twomiao/desktop/download';

foreach ($urls as $url) {
    $movieParser = new MovieParser($url, new Xc0125());
    (new Downloader($movieParser, $local))
        ->run();
}