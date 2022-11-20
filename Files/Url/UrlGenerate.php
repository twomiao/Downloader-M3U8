<?php
namespace Downloader\Files\Url;

use Downloader\Runner\Contracts\GenerateUrlInterface;
use Downloader\Runner\TransportStreamFile;

class UrlGenerate implements GenerateUrlInterface
{
    private string $url;

    public function __construct(string $url)
    {
        $this->url = $url;
    }

    public function generateUrl(TransportStreamFile $file): string
    {
        if ($file->getUrl() === "/headers/header.ts")
        {
            return "";
        }
        return ltrim($this->url, '\/').'/'.$file->getUrl();
    }
}