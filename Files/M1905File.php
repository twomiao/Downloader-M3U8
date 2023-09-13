<?php declare(strict_types=1);
namespace Downloader\Files;

use Downloader\Runner\Contracts\EncryptedFileInterface;
use Downloader\Runner\FileM3u8;
use Downloader\Runner\FileSlice;

/**
 * @final
 * Class TestFile
 * @package Downloader\Files
 */
final class M1905File extends FileM3u8 implements EncryptedFileInterface
{
    /**
     * 下载域名
     * @var string $downloadDomainName
     */
    public static string $downloadDomainName = "www.1905.com";

    public function decrypt(string $data): string
    {
        // return openssl_decrypt($fileData, 'aes-128-cbc', '6a1177f9ceedcdcf', OPENSSL_RAW_DATA);
//        return openssl_decrypt($fileData, 'aes-128-cbc', '6a28df8dbb9eacfd', OPENSSL_RAW_DATA);
        return $data;
    }
    
    public function downloadCdnUrl(FileSlice $fileSlice) : string 
    {
        return "{$this->cdnUrl}/".$fileSlice->getPath();
    }
}