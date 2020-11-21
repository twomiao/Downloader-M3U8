<?php
namespace Downloader\Runner\Decrypt;

interface DecryptionInterface
{
    public function decrypt(string $data, string $key = '', string $iv = ''): ?string;
}