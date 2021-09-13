<?php
namespace Downloader\Parsers;

use Downloader\Runner\Parser;
class M1905 extends Parser
{
    static function tsUrl(string $m3u8FileUrl, string $partTsUrl): string
    {
        return dirname($m3u8FileUrl) . '/' . $partTsUrl;
    }

    static function fileName(string $m3u8FileUrl): string
    {
        return basename($m3u8FileUrl, '.m3u8');
    }
}