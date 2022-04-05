<?php
namespace Downloader\Runner;

use Symfony\Contracts\EventDispatcher\Event;

class CreateVideoFileEvent extends Event
{
    public const NAME = 'video.create.file';

    protected FileM3u8 $file;

    public function __construct(FileM3u8 $file)
    {
        $this->file = $file;
    }

    public function getFileM3u8(): FileM3u8
    {
        return $this->file;
    }

    public function getSuffix(): string {
        return 'mp4';
    }
}