<?php
declare(strict_types=1);
namespace Downloader\Runner\Contracts;

use Downloader\Runner\FileSlice;
use Downloader\Runner\TransportStreamFile;

interface EncryptedFileInterface
{
    /**
     * 解密文件
     * @param string $data
     * @return string
     */
    public function decrypt(string $data): string;
}