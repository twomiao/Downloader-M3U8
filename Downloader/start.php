<?php
require dirname(__DIR__) . '/vendor/autoload.php';

$downloader = new \Downloader\Runner\Downloader(
    $container = require __DIR__ . '/Runner/Container.php',
    $config = [
        'output' => dirname(__DIR__) . '/../output',
        'concurrent' => 40,
    ]
);


$downloader
    ->setMovieParser(new \Downloader\Parsers\huayunw(), [

    ])
    ->setMovieParser(new \Downloader\Parsers\YouKu(), [
        "https://leshi.cdn-zuyida.com/20171011/M38WUkYv/1000kb/hls/index.m3u8"
    ])
    ->run();
