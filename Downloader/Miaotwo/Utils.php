<?php

namespace Downloader\Miaotwo;

class Utils
{
    public static function baseInfo()
    {
        $logo = " ___                          _                   _                 __  __   ____         ___
 |   \   ___  __ __ __  _ _   | |  ___   __ _   __| |  ___   _ _    |  \/  | |__ /  _  _  ( _ )
 | |) | / _ \ \ V  V / | ' \  | | / _ \ / _` | / _` | / -_) | '_|   | |\/| |  |_ \ | || | / _ \
 |___/  \___/  \_/\_/  |_||_| |_| \___/ \__,_| \__,_| \___| |_|     |_|  |_| |___/  \_,_| \___/\n";
        $intro = "特点：运行平台Linux系统 - 协程并发 - 高速下载M3U8视频 - 自定义并发速度 - 自定义下载任务.\n\n";
        $boot = "启动：" . date('Y-m-d H:i:s', time()) . "\n\n";
        $info = "环境：Swoole:" . SWOOLE_VERSION .
            ", PHP: v" . phpversion() .
            ", Os: " . PHP_OS .
            ", Downloader: v" . Downloader::VERSION;

        return "\e[1;32m {$logo}\n\r\e[0m \e[1;30m{$intro} {$boot} {$info}.\e[0m" . PHP_EOL;
    }

    public static function fileSize($bytes)
    {
        if ($bytes > 1024 * 1024 * 1024 * 1024) {
            return round($bytes / (1024 * 1024 * 1024 * 1024), 1) . "TB";
        }
        if ($bytes > 1024 * 1024 * 1024) {
            return round($bytes / (1024 * 1024 * 1024), 1) . "GB";
        }
        if ($bytes > 1024 * 1024) {
            return round($bytes / (1024 * 1024), 1) . "MB";
        }
        if ($bytes > 1024) {
            return round($bytes / (1024), 1) . "KB";
        }
        return $bytes . "B";
    }

    public static function timeCost(string $seconds)
    {
        $remian = time() - $seconds;

        if ($remian >= 24 * 3600 * 7) {
            return round($remian / (24 * 3600 * 7), 2) . "周";
        }

        if ($remian >= 24 * 3600) {
            return round($remian / (24 * 3600), 2) . " 天";
        }

        if ($remian >= 3600) {
            return round($remian / 3600, 2) . " 小时";
        }

        if ($remian >= 60) {
            return round($remian / 60, 2) . " 分钟";
        }

        return $remian . ' 秒';
    }

    public static function downloadSpeed(int $timeNow, int $downloadedSize = 0)
    {
        $secondSpeed = $downloadedSize / $timeNow;
        return self::fileSize(round($secondSpeed, 2)) . '/s';
    }
}