<?php
namespace Downloader\Parsers;
use Downloader\Miaotwo\MovieParserInterface;

/**
 * Class Xc0125
 * @package Downloader\Parsers
 */
class Xc0125 implements MovieParserInterface
{
    public function parsedTsUrl($m3u8Url, $ts): string
    {
        $length = strrpos($m3u8Url, '/') + 1;
        return substr($m3u8Url, 0, $length) . $ts;
    }
}