<?php
declare(strict_types=1);

namespace Downloader\Runner;

use Symfony\Contracts\EventDispatcher\Event as EventDispatcherEvent;

class SaveFile extends EventDispatcherEvent
{
    const NAME = 'saving_file';  

    public function __construct(public FileM3u8 $file) {

    }
}