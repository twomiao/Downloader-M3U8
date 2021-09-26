<?php

namespace Downloader\Runner;

use Throwable;

class DownloaderException extends \Exception
{
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public static function timeout($message = "", $code = 1000)
    {
        return new static($message, $code);
    }

    public static function valid($message = "", $code = 1002)
    {
        return new static($message, $code);
    }

}