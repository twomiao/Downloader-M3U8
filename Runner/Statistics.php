<?php
declare(strict_types=1);
namespace Downloader\Runner;

final class Statistics
{
    // 已经保存
    public const SAVED = 4;
    // 正在下载 
    public const DOWNLOADING = 5;
    // 待下载
    public const WAITING = 6;
    // 下载完成
    public const DOWNLOAD_OK = 7;
    // 下载失败
    public const DOWNLOAD_ERROR = 8;

    public function __construct(
        public int $total = 0,
        public int $succeedNum = 0,
        public int $errors = 0,
        public int $flag = self::WAITING)
    {
        
    }
}