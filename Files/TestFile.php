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
final class TestFile extends FileM3u8 implements GenerateUrlInterface,DecryptFileInterface
{
    public static function generateUrl(TransportStreamFile $file): string
    {
        $path = $file->getUrl();
        return "https://xxx.com/{$path}";
    }

    public function decrypt(string $fileData, FileM3u8 $fileM3u8): string
    {
        return openssl_decrypt($fileData, 'aes-128-cbc', '9d2d4fcf98fb99aa', OPENSSL_RAW_DATA);
    }
}