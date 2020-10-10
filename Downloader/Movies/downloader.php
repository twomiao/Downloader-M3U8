<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Downloader\Miaotwo\Logger;
use Downloader\Miaotwo\MovieParser;
use Downloader\Miaotwo\Downloader;
use Downloader\Miaotwo\Utils;
use Downloader\Parsers\BaoYun29;

$urls = [
    ""
];

$local = '/mnt/c/Users/twomiao/desktop/download';

// 开始时间
$run_at = time();

foreach ($urls as $url) {
    $movieParser = new MovieParser($url, new BaoYun29());
    (new Downloader($movieParser, $local))
        ->maxConcurrent(5)
        ->run();
}

// 结束时间
$end_at = Utils::timeCost($run_at);
Logger::create()->info("下载完成用时：{$end_at}", '[ Done ] ');