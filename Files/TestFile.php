<?php declare(strict_types=1);
namespace Downloader\Files;

use Downloader\Runner\Contracts\DecryptFileInterface;
use Downloader\Runner\Contracts\GenerateUrlInterface;
use Downloader\Runner\FileM3u8;
use Downloader\Runner\TransportStreamFile;

/**
 * @final
 * Class TestFile
 * @package Downloader\Files
 */
//final class TestFile extends FileM3u8 implements GenerateUrlInterface,DecryptFileInterface
final class TestFile extends FileM3u8 implements GenerateUrlInterface
{
    public static function generateUrl(TransportStreamFile $file): string
    {
        $path = $file->getUrl();
        $url  = $file->getFileM3u8()->getUrl();
        return dirname($url)."/{$path}";
    }

    public function decrypt(string $fileData, FileM3u8 $fileM3u8): string
    {
        return openssl_decrypt($fileData, 'aes-128-cbc', '4c044a4addda9ea9', OPENSSL_RAW_DATA);
    }
}