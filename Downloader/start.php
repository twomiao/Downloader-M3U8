<?php declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';

use Downloader\Runner\Downloader;
use Downloader\Runner\Decrypt\Aes128;
use Downloader\Parsers\YouKu;
use Downloader\Parsers\Hua;

\Co\run(function () {

    \Swoole\Runtime::enableCoroutine(true, SWOOLE_HOOK_ALL);

    $downloader = new Downloader(
        $container = require __DIR__ . '/Runner/Container.php',
        $config = [
            'output' => dirname(__DIR__) . '/../output2',
            'concurrent' => 25,
        ]
    );

    $downloader
        ->setMovieParser(new YouKu(), [
            "https://youku.com-movie-youku.com/20181028/1275_c4fb695f/1000k/hls/index.m3u8",
            "https://dalao.wahaha-kuyun.com/20201114/259_7e8e3c78/1000k/hls/index.m3u8"
        ], new Aes128())
        ->setMovieParser(new Hua(), [
            "https://m3u8i.vodfile.m1905.com/202011220309/972a4a041420ecca90901d33fa2086ee/movie/2017/06/15/m201706152917FI77DD7VW2PA/AF9889E7AAB81F8C1AE5615AD.m3u8"
        ], new Aes128())
        ->run();
});