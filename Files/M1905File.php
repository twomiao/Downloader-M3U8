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
final class M1905File extends FileM3u8
//    implements DecryptFileInterface
{
    public function decrypt(string $fileData, TransportStreamFile $transportStreamFile): string
    {
        return openssl_decrypt($fileData, 'aes-128-cbc', '6a1177f9ceedcdcf', OPENSSL_RAW_DATA);
//        return openssl_decrypt($fileData, 'aes-128-cbc', '6a28df8dbb9eacfd', OPENSSL_RAW_DATA);
    }
}