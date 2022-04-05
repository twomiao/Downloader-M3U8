<?php
declare(strict_types=1);
namespace Downloader\Runner\Contracts;
use Downloader\Runner\FileM3u8;

interface DecryptFileInterface
{
    /**
     * 解密文件
     * @param string $fileData
     * @param FileM3u8 $fileM3u8
     * @return string
     */
    public function decrypt(string $fileData, FileM3u8 $fileM3u8): string;
}