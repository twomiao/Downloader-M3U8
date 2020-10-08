<?php

namespace Downloader\Miaotwo;

interface MovieParserInterface
{
    public function parsedTsUrl($m3u8Url, $movieTs): string;
}