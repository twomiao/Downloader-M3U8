<?php
namespace Downloader\Runner;

class MovieParserException extends DownloaderException
{
    protected $message = 'Movie parsing interface address is abnormal.';
    protected $code    = 10001;
}