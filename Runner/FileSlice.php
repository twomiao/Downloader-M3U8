<?php
namespace Downloader\Runner;

use Swoole\Coroutine\System;
use SplFileInfo;

class FileSlice extends SplFileInfo
{
    /**
     * @property string $filename
     */
    protected string $filename;


    public function __construct(
        protected string $path,
        public FileM3u8 $file
    ) {
        $this->filename = $file->subDirectory . DIRECTORY_SEPARATOR. \rtrim(pathinfo($path, PATHINFO_BASENAME), ".ts") . ".ts";
        parent::__construct($this->filename);
    }

    public function delete(): bool
    {
        clearstatcache();
        if (is_file($this->filename())) {
            return @unlink($this->filename);
        }
        return true;
    }

    public function save(string $data) : int
    {
        return (int)System::writeFile($this->filename(), $data, FILE_APPEND);
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function filename(): string
    {
        return $this->filename;
    }
}
