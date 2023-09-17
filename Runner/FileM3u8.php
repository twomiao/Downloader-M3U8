<?php
declare(strict_types=1);

namespace Downloader\Runner;

use Downloader\Runner\Contracts\EncryptedFileInterface;
use RuntimeException;
use SplFileInfo;
use SplObjectStorage;

/**
 * Class FileM3u8
 * @package Downloader\Runner
 */
abstract class FileM3u8  extends SplFileInfo implements \Countable
{
    /**
     * @property string $url
     */
    protected string $downloadUrl;

    /**
     * 文件分片
     * @property SplObjectStorage $fileSlice
     */
    public ?\SplObjectStorage $fileSlice = null;

    /**
     * @property string $savePath
     */
    public string $savePath;

    /**
     * @property int $fileSize
     */
    public int $fileSize = 0;

    /**
     * 下载域名
     * @property string $downloadDomainName
     */
    public static string $downloadDomainName = "N/A";

    /**
     * 任务下载统计
     * @property TaskFinished $taskFinished
     */
    public TaskFinished $taskFinished;

    /**
     * FileM3u8 constructor.
     * @param string $url
     * @param string $absolutePath
     * @param string $filename
     */
    public function __construct(public string $m3u8Url, 
            protected string $filename,
            public string $cdnUrl,
            protected string $ext)
    {
        $this->savePath = Downloader::$savePath . DIRECTORY_SEPARATOR . $filename;
        $this->filename =  "{$this->savePath}.{$this->ext}";
        $this->fileSlice = new \SplObjectStorage();
        $this->taskFinished = new TaskFinished(0);
        parent::__construct($this->filename);
    }

    public function addFlieSlices(FileSlice ...$fileSlices) : static {
        foreach($fileSlices as $fileSlice) {
            $fileSlice->file($this);
            $this->fileSlice->attach($fileSlice, $fileSlice);
        }
        return $this;
    }

    public function resetTaskFinished() : void {
        $this->taskFinished->succeedNum = 0;
    }

    public function getFileName() : string {
        return $this->filename;
    }

    public function tmpFilename() : string {
        return $this->savePath . '_tmp';
    }

    public function deleteTempFile() : void {
        $tmpFile = $this->tmpFilename();
        \clearstatcache();
        if(is_file($tmpFile))
        {
           unlink($tmpFile);
        }

        foreach ($this->fileSlice as $fileSlice)
        {
            $fileSlice instanceof FileSlice && $fileSlice->delete();
        }
        $this->fileSlice = null;
        if (is_dir($this->savePath)) {
            @rmdir($this->savePath);
        }
    }

    public function succeed() {
        return match($this->taskFinished->flag)
        {
            TaskFinished::FLAG_SAVE_FILE_SUCCEED,
            TaskFinished::FLAG_LOCAL_FILE_EXISTS => true,
            default => false
        };
    }

    abstract public function downloadCdnUrl(FileSlice $fileSlice) : string;

    /**
     * 加密KEY
     * @return string
     */
    public static function getSecretKey(string $data) : string
    {
        \preg_match('@URI="(.*?)"@is', $data, $matches);

        return $matches[1] ?? '';
    }

    /**
     * 加密算法名称
     * @return string
     */
    public static function getMethodKey(string $data) : string
    {
        \preg_match('@EXT-X-KEY:METHOD=(.*?),@is', $data, $matches);

        return $matches[1] ?? '';
    }

    /**
     * 版本号
     * @return int
     */
    public static function getVersion(string $data) : int
    {
        \preg_match('/#EXT-X-VERSION:(\d+)/is', $data, $res);

        return intval($res[1] ?? 0);
    }

    /**
     * 最大时间
     * @return float
     */
    public static function getMaxTime(string $data) : float
    {
        \preg_match('/#EXT-X-TARGETDURATION:(\d+)/is', $data, $res);

        return \floatval($res[1] ?? 0);
    }

    /**
     * 时间集合数组
     * @return array
     */
    public static function getTimeList(string $data) : array
    {
        \preg_match_all("@#EXTINF:(.*?),@is", $data, $res);

        return $res[1] ?? [] ;
    }

    /**
     * 获取文件视频片段路径
     * @return array
     */
    public static function getPathList(string $data) : array
    {
        \preg_match_all("/,[\s](.*?\.ts)/is", $data, $res);

        return ($res[1]) ?? [];
    }

    public function isEncrypted(): bool 
    {
      return  $this instanceof EncryptedFileInterface;
    }
    
    public function count(): int
    {
        if (is_null($this->fileSlice))
        {
            throw new RuntimeException("File shard is empty.");
        }
        return $this->fileSlice->count();
    }

    public function getSizeformat(): string
    {
        if (!is_file($this->filename))
        {
           return "0";
        }
        $size = (int)$this->getSize();
        $map = ['Byte', 'KB', 'MB', 'GB'];
        for ($p = 0; $size >= 1024 && $p < 3; $p++) {
            $size /= 1024;
        }
        return sprintf("%0.3f %s", $size,  $unit = $map[$p]);
    }

    public function getMimeType() {
        if(!is_file($this->filename)) {
            return "N/A";
        }
        return \mime_content_type($this->filename);
    }
}