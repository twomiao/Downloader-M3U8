<?php
namespace Downloader\Files\Decrypt;

use Downloader\Runner\Contracts\DecryptFileInterface;
use Downloader\Runner\TransportStreamFile;

class M1906DecryptFile implements DecryptFileInterface
{
    /**
     * 6a1177f9ceedcdcf
     * @var string $key
     */
    private string $key;

    /**
     * aes-128-cbc
     * @var string $method
     */
    private string $method;

    /**
     * 初始化完成
     * M1906 constructor.
     * @param string $key
     * @param string $method
     */
    public function __construct(string $key, string $method)
    {
        $this->key = $key;
        $this->method = $method;
    }

    public function decrypt(string $fileData, TransportStreamFile $transportStreamFile): string
    {
        return openssl_decrypt($fileData, $this->method, $this->key, OPENSSL_RAW_DATA);
    }
}