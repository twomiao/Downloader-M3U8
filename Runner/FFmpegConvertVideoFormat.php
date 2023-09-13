<?php
declare(strict_types=1);

namespace Downloader\Runner;

use SplObjectStorage;
use Symfony\Contracts\EventDispatcher\Event as EventDispatcherEvent;

class FFmpegConvertVideoFormat  extends EventDispatcherEvent
{
    const NAME = 'ffmpeg.convert.video.file';  

    public function __construct(protected SplObjectStorage $files) {

    }

    public function downloadedFiles() : SplObjectStorage {
        return $this->files;
    }

}