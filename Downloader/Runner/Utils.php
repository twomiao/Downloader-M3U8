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

    public static function isDir($dir)
    {
        clearstatcache();
        return is_dir($dir);
    }

    public static function mkdirDirectory($dir, $mode = 0777)
    {
        if (empty($dir)) return false;

        if (!self::isDir($dir)) {
            return @mkdir($dir, $mode, true);
        } else {
            @chmod($dir, $mode);
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
}