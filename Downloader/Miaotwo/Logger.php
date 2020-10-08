<?php

namespace Downloader\Miaotwo;

class Logger
{
    public static function create()
    {
        return new static();
    }

    public function warn($info, $title = '[ - ] ')
    {
        echo "\033[1;33m {$title}{$info}\033[0m\n";
    }

    public function info($info, $title = '[ - ] ')
    {
        echo "\033[0;32m {$title}{$info}\033[0m\n"; //0;32
    }

    public function error($info, $title = '[ - ] ')
    {
        echo "\033[1;31m {$title}{$info}\033[0m\n"; //0;32
    }

    public function debug($info, $title = '[ - ] ')
    {
        echo " {$title}{$info}\n";
    }
}
