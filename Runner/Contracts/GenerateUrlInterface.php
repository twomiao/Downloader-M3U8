<?php
namespace Downloader\Runner\Contracts;

use Downloader\Runner\TransportStreamFile;

interface GenerateUrlInterface
{
    /**
     * 读取在线ts视频文件
     * @param TransportStreamFile $file
     * @return string
     */
    public function generateUrl(TransportStreamFile $file): string;
}