<?php
namespace Downloader\Runner\Decrypt;

class Aes128 implements DecryptionInterface
{
    protected $key;

    public function setKey($key): void
    {
        $this->key = $key;
    }

    public function getkey(): ?string
    {
        return $this->key;
    }

    public function decrypt(string $data, string $key = '', string $iv = ''): ?string
    {
        $this->key = $key;
        return openssl_decrypt($data, 'AES-128-CBC', $this->key, OPENSSL_RAW_DATA);
    }
}