<?php
namespace Downloader\Runner;

use RuntimeException;
use SplFileInfo;
use Swoole\Coroutine\System;

class FileSlice extends SplFileInfo
{
    /**
     * @property int
     */
    const STATE_SUCCESS = 1;

    /**
     * @property int
     */
    const STATE_FAIL = 2;

    /**
     * @property string $filename
     */
    protected string $filename;

    /**
     * @property int $downloadStatus
     */
    public int $downloadStatus;

    /**
     * 属于哪个文件
     * @property $belongsToFile FileM3u8
     */
    protected FileM3u8 $belongsToFile;

    public function __construct(
        protected string $path,
        protected string $savePath)
    {
        $this->filename = $this->savePath.\rtrim(pathinfo($path, PATHINFO_BASENAME),".ts").".ts";
        parent::__construct($this->filename);
    }

    public function file(FileM3u8 $file) : void
    {
        $this->belongsToFile = $file;
    }

    public function belongsToFile() : FileM3u8 {
        if(is_null($this->belongsToFile))
        {
            throw new RuntimeException("File ownership is empty.");
        }
        return $this->belongsToFile;
    }

    public function setDownloadStatus(int $downloadStatus) : void {
        $this->downloadStatus = $downloadStatus;
    }

    public function downloadSuccess() : bool {
       return $this->downloadStatus === static::STATE_SUCCESS;
    }

    public function delete() : bool
    {
        clearstatcache();
        if (is_file($this->filename()))
        {
            return unlink($this->filename());
        }
        return true;
    }

    public function getPath() : string {
        return $this->path;
    }

    public function filename() : string {
        return $this->filename;
    }

    // /**
    //  * @param string|null $data
    //  * @param bool $overwrite
    //  * @return int
    //  * @throws \Exception
    //  */
    // public function saveFile(string $data, bool $overwrite = false) :int
    // {
    //     if($this->exists() && !$overwrite) {
    //        return \filesize($this->filePath);
    //     }

    //     if ($this->fileM3u8->isEncryptFile()) {
    //         $data = $this->fileM3u8->decryptFile()->decrypt($data, $this);
    //     }

    //     if (System::writeFile($this->filePath, $data) === false) {
    //         throw new \RuntimeException('文件保存失败:'.$this->filePath);
    //     }
    //     return \filesize($this->filePath);
    // }
}
