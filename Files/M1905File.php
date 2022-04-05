<?php declare(strict_types=1);
namespace Downloader\Files;

use Downloader\Runner\Contracts\GenerateUrlInterface;
use Downloader\Runner\FileM3u8;
use Downloader\Runner\TransportStreamFile;

final class M1905File extends FileM3u8 implements GenerateUrlInterface
{
    public static function generateUrl(TransportStreamFile $file): string
    {
        $url = $file->getFileM3u8()->getUrl();

        return dirname($url).'/'.trim($file->getUrl());
    }
}