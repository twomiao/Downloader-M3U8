<?php

namespace Downloader\Runner;

use Swoole\Coroutine\System;
use SplFileInfo;

class FileSlice extends SplFileInfo
{
    public function __construct(
        protected string $path,
        public FileM3u8 $file
    ) {
        parent::__construct($this->filename());
    }

    public function delete(): bool
    {
        $filename = $this->filename();
        clearstatcache(true, $filename);
        $this->file = null;
        if (is_file($filename)) {
            return @unlink($filename);
        }
        return false;
    }

    public function save(string $data): int
    {
        return (int)System::writeFile($this->filename(), $data, FILE_APPEND);
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function filename(): string
    {
        if (!$this->file) {
            return "";
        }
        $basename = \trim($this->file->getBasename(), ".ts");
        return $this->file->subDirectory . DIRECTORY_SEPARATOR . "{$basename}.ts";
    }
}
