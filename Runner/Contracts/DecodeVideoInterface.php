<?php
declare(strict_types=1);
namespace Downloader\Runner\Contracts;
use Downloader\Runner\FileM3u8;

interface DecodeVideoInterface
{
    /**
     * 解码视频
     * @param FileM3u8 $fileM3u8
     * @param string $data
     * @param string $remoteTsUrl
     * @return string
     */
    public static function decode(FileM3u8 $fileM3u8, string $data, string $remoteTsUrl): string;

    /**
     * 秘钥KEY
     * @param FileM3u8 $fileM3u8
     * @return string
     */
    public static function key(FileM3u8 $fileM3u8): string;

    /**
     * 加密方式
     * @param FileM3u8 $fileM3u8
     * @return string
     */
    public static function method(FileM3u8 $fileM3u8): string;
}