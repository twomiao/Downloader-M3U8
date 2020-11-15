<?php

namespace Downloader\Runner;

interface MovieParserInterface
{
    public function parsedTsUrl($m3u8Url, $movieTs): string;
}