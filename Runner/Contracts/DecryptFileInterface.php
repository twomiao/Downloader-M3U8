<?php
declare(strict_types=1);
namespace Downloader\Runner\Contracts;
use Downloader\Runner\TransportStreamFile;

interface DecryptFileInterface
{
    /**
     * 解密文件
     * @param string $fileData
     * @param TransportStreamFile $transportStreamFile
     * @return string
     */
    public function decrypt(string $fileData, TransportStreamFile $transportStreamFile): string;
}