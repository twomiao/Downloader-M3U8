<?php
use Downloader\Miaotwo\Logger;
use Downloader\Miaotwo\MovieParser;
use Downloader\Parsers\M1905;
use Downloader\Miaotwo\Downloader;
use Downloader\Miaotwo\Utils;

require_once __DIR__ . '/../../vendor/autoload.php';

$urls = [
    "https://m3u8i.vodfile.m1905.com/202010090950/878cc64e3a4e3f51ecf6bc62e47de99c/movie/2020/04/13/m20200413FALLJ3N5EYOUMUZ7/906690547B38AFF136261A85B.m3u8"
    // 添加更多M3U8地址信息 .....
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
