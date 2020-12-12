<?php declare(strict_types=1);

namespace Downloader\Runner;

class Utils
{
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

    public static function isDir($dir)
    {
        clearstatcache();
        return is_dir($dir);
    }

    public static function isFile($file)
    {
        clearstatcache();
        return is_file($file);
    }

    public static function mkdirDiectory($dir, $mode = 0777)
    {
        if (empty($dir)) return false;

        if (!self::isDir($dir)) {
            return @mkdir($dir, $mode, true);
        } else {
            @chmod($dir, $mode);
        }
        return true;
    }

    public static function touchFile($file, $mode = 0777)
    {
        if (!self::isFile($file) && $success = @touch($file)) {
            @chmod($success, $mode);
            return $success;
        }
        return true;
    }

    public static function writeFile($file, $content, $append = false)
    {
        if ($append) {
            return @file_put_contents($file, $content, FILE_APPEND);
        }
        return @file_put_contents($file, $content);
    }

    public static function downloadSpeed(int $timeNow, int $downloadedSize = 0)
    {
        $secondSpeed = $downloadedSize / $timeNow;
        return self::fileSize(
                round($secondSpeed, 2)
            ) . '/s';
    }
}