<?php

declare(strict_types=1);

namespace Downloader\Runner;

use Downloader\Runner\Contracts\EncryptedFileInterface;
use SplFileInfo;
use Swoole\Coroutine\System;

/**
 * Class FileM3u8
 * @package Downloader\Runner
 */
abstract class FileM3u8 extends SplFileInfo implements \Countable
{
    /**
     * @property string $url
     */
    protected string $downloadUrl;

    /**
     * 文件分片
     * @property array $fileSlice
     */
    public array $fileSlice = [];

    /**
     * @property string $subDirectory
     */
    public string $subDirectory;

    /**
     * 文件绝对路径
     * @property string $file
     */
    protected string $file;

    /**
     * 下载域名
     * @property string $downloadDomainName
     */
    public static string $downloadDomainName = "N/A";

    /**
     * 统计
     * @property Statistics $statistics;
     */
    public Statistics $statistics;

    /**
     * 文件id
     * @property string $id
     */
    protected string $id;

    /**
     * FileM3u8 constructor.
     * @param string $url
     * @param string $absolutePath
     * @param string $filename
     */
    public function __construct(
        public string $m3u8Url,
        protected string $filename,
        public string $cdnUrl
    ) {
        $savePath = Container::make('video_save_path');
        // 子目录
        $this->subDirectory = $this->subDirectory($savePath);
        // 文件绝对路径
        $this->file =  $this->subDirectory . DIRECTORY_SEPARATOR . "{$filename}.mp4";
        $this->id = md5($this->m3u8Url);
        $this->statistics = new Statistics();
        parent::__construct($this->file);
    }

    protected function subDirectory(string $savePath): string
    {
        return  $savePath . DIRECTORY_SEPARATOR . $this->filename;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function addFlieSlices(FileSlice ...$fileSlices): static
    {
        foreach($fileSlices as $fileSlice) {
            $this->fileSlice[$fileSlice->getBasename(".ts")] = $fileSlice;
        }
        return $this;
    }

    public function getFileName(): string
    {
        return $this->filename;
    }

    public function saved(): bool
    {
        return $this->statistics->flag === Statistics::SAVED;
    }

    public function tmpFilename(): string
    {
        return $this->subDirectory . DIRECTORY_SEPARATOR . "{$this->filename}_tmp";
    }

    public function statistics(): Statistics
    {
        return $this->statistics;
    }

    public function downloding(): bool
    {
        return $this->statistics()->flag === Statistics::DOWNLOADING;
    }

    public function downloadSuccess(): bool
    {
        return $this->statistics()->flag = Statistics::DOWNLOAD_OK;
    }

    public function downloadError(): bool
    {
        return $this->statistics()->flag = Statistics::DOWNLOAD_ERROR;
    }

    public function save(): int
    {
        $filesize = 0;
        $tempFile = $this->tmpFilename();
        \clearstatcache(true, $tempFile);
        if(\is_file($tempFile)) {
            return \stat($tempFile)["size"];
        }
        foreach ($this->fileSlice as $fileSlice) {
            if ($fileSlice instanceof FileSlice) {
                $data = System::readFile($fileSlice->filename());
                $filesize += (int)System::writeFile($tempFile, $data, FILE_APPEND);
            }
        }
        return $filesize;
    }

    public function deleteTempFile(): void
    {
        $tmpFile = $this->tmpFilename();
        \clearstatcache();
        if(is_file($tmpFile)) {
            unlink($tmpFile);
        }
        
        foreach ($this->fileSlice as $fileSlice) {
            $fileSlice instanceof FileSlice && $fileSlice->delete();
        }
        if (is_dir($this->subDirectory)) {
            @rmdir($this->subDirectory);
        }
        $this->fileSlice = [];
    }

    abstract public function fileSliceUrl(FileSlice $fileSlice): string;

    /**
     * 加密KEY
     * @return string
     */
    public static function getSecretKey(string $data): string
    {
        \preg_match('@URI="(.*?)"@is', $data, $matches);

        return $matches[1] ?? '';
    }

    /**
     * 加密算法名称
     * @return string
     */
    public static function getMethodKey(string $data): string
    {
        \preg_match('@EXT-X-KEY:METHOD=(.*?),@is', $data, $matches);

        return $matches[1] ?? '';
    }

    /**
     * 版本号
     * @return int
     */
    public static function getVersion(string $data): int
    {
        \preg_match('/#EXT-X-VERSION:(\d+)/is', $data, $res);

        return intval($res[1] ?? 0);
    }

    /**
     * 最大时间
     * @return float
     */
    public static function getMaxTime(string $data): float
    {
        \preg_match('/#EXT-X-TARGETDURATION:(\d+)/is', $data, $res);

        return \floatval($res[1] ?? 0);
    }

    /**
     * 时间集合数组
     * @return array
     */
    public static function getTimeList(string $data): array
    {
        \preg_match_all("@#EXTINF:(.*?),@is", $data, $res);

        return $res[1] ?? [] ;
    }

    /**
     * 获取文件视频片段路径
     * @return array
     */
    public static function getPathList(string $data): array
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
        return \count($this->fileSlice);
    }

    public function getSizeformat(): string
    {
        $size = $this->getSize();
        if ($size === false) {
            return "0 bytes";
        }
        $size = (int)$size;
        $units = ['Byte', 'KB', 'MB', 'GB'];
        for ($p = 0; $size >= 1024 && $p < 3; $p++) {
            $size /= 1024;
        }
        return sprintf("%0.3f %s", $size, $units[$p]);
    }

    public function __toString(): string
    {
        return $this->file;
    }
}
