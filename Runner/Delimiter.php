<?php
namespace Downloader\Runner;

trait Delimiter
{
    /**
     * 磁盘分隔符
     * @param string $directory
     * @return string
     */
    public static function delimiter(string $directory) : string
    {
        if (hash_equals('WINNT', PHP_OS)) {
            return str_replace('/', '\\', $directory);
        }
        return str_replace('\\', '/', $directory);
    }
}