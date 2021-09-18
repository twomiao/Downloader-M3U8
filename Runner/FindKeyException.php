<?php


namespace Downloader\Runner;


use Throwable;

class FindKeyException extends \Exception
{
    public function __construct($message = "Find the key, try to decrypt.", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

}