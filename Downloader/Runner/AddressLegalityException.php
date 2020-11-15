<?php
namespace Downloader\Runner;

use Throwable;

class AddressLegalityException extends DownloaderException
{
    protected $code = 10002;

    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        $message = "Invalid address exception:{$message}";
        parent::__construct($message, $code, $previous);
    }
}