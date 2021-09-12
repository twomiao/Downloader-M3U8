<?php
declare(strict_types=1);
namespace Downloader\Runner;

class PartTs
{
    /**
     *  ts 时长
     * @var float $playTime
     */
    protected float $playTime = 0.0;

    /**
     * ts 名称
     * @var string $filename
     */
    protected string $filename;
    /**
     * ts 下载本地文件位置
     * @var string $putFile
     */
    protected string $putFile;

    /**
     * ts 路径
     * @var mixed|string
     */
    protected string $tsPath;

    /**
     * ts url
     * @var string $tsUrl
     */
    protected string $tsUrl;

    /**
     * m3u8File 文件名称
     * @var string $m3u8Filename
     */
    public string $m3u8Filename;

    public function __construct(string $putDir, float $playTIme, string $tsUrl)
    {
        $this->tsUrl = $tsUrl;
        $this->tsPath = parse_url($tsUrl, PHP_URL_PATH);
        $this->filename = basename($this->tsPath); // ts 文件名称
        $this->putFile = $putDir . '/' . $this->filename;
        $this->playTime = $playTIme;
    }

    public function setMu38Name(string $filename): void
    {
        $this->m3u8Filename = $filename;
    }

    /**
     * @return int
     */
    public function getFileSize(): int
    {
        $fileSize = 0;
        if (file_exists($this->putFile)) {
            $fileSize = filesize($this->putFile);
        }
        return $fileSize;
    }

    public function putFile($content): bool
    {
        if (file_exists($this->tsPath)) {
            return true;
        }
        return file_put_contents($this->putFile, $content) > 0;
    }

    /**
     * @return mixed|string
     */
    public function getTsPath()
    {
        return $this->tsPath;
    }

    /**
     * @return string
     */
    public function getTsUrl(): string
    {
        return $this->tsUrl;
    }

    /**
     * @return string
     */
    public function getPutFile(): string
    {
        return $this->putFile;
    }

    /**
     * @return bool
     */
    public function exists(): bool
    {
        return file_exists($this->putFile);
    }

    public function delete(): bool
    {
        if (file_exists($this->putFile)) {
            return unlink($this->putFile);
        }
        return false;
    }

    /**
     * @return mixed
     */
    public function getFilename()
    {
        return $this->filename;
    }

    public function __toString()
    {
        return $this->tsUrl;
    }
}