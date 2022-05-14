<?php
namespace Downloader\Runner;

use Swoole\Coroutine\System;

class TransportStreamFile
{
    /**
     * @var int
     */
    const STATE_SUCCESS = 1;

    /**
     * @var int
     */
    const STATE_FAIL    = 2;

    /**
     * 播放时长
     * @var float $duration
     */
    private float $duration = 0.0;

    /**
     * 视频片段长度
     * @var string $url
     */
    private string $url;

    /**
     * 文件路径
     * @var string $filePath
     */
    private string $filePath;

    /**
     * ts 文件名
     * @var string $filename
     */
    private string $filename;

    /**
     * 文件状态
     * @var int $state
     */
    private int $state;

    /**
     * 文件对象
     * @var null|FileM3u8 $fileM3u8
     */
    private ?FileM3u8 $fileM3u8 = null;

    /**
     * TransportStreamFile constructor.
     * @param string $url
     * @param string $filename
     * @param float $duration
     * @param string $absolutePath
     */
    public function __construct(string $url, string $filename, float $duration, string $absolutePath)
    {
        $this->url = $url;
        $suffix = \strripos(\trim($filename),'.ts') === false ? 'ts' : '';
        $this->filename = \trim($filename).".{$suffix}";
        $this->filePath = rtrim($absolutePath,'\/'). DIRECTORY_SEPARATOR.$this->filename;
        $this->duration = $duration;
        \restore_error_handler();
    }

    /**
     * @param int $state
     */
    public function setState(int $state):void {
        if (!in_array($state, [self::STATE_SUCCESS, self::STATE_FAIL], true)) {
            return;
        }
        $this->state = $state;
    }

    public function getState() : int {
        return $this->state;
    }

    public function setFileM3u8(FileM3u8 $file) : void {
        $this->fileM3u8 = $file;
    }

    public function getFileM3u8():?FileM3u8 {
        return $this->fileM3u8;
    }

    public function getFilename() : string {
        return $this->filename;
    }

    /**
     * @param string|null $data
     * @param bool $overwrite
     * @return int
     * @throws \Exception
     */
    public function saveFile(string $data, bool $overwrite = false) :int
    {
        if($this->exists() && !$overwrite) {
           return \filesize($this->filePath);
        }

        if (Downloader::isModel(Downloader::MODE_JSON) && $this->fileM3u8->isEncryptFile()) {
            // 可以进行二次验证
            $data = $this->fileM3u8->jsonDecrypt(
                $data,
                $this->fileM3u8->getJsonFile('key'),
                $this->fileM3u8->getJsonFile('method'),
            );
        } else if ($this->fileM3u8->isEncryptFile()) {
            $data = $this->fileM3u8->decrypt($data, $this->fileM3u8);
        }
        if (System::writeFile($this->filePath, $data) === false) {
            throw new \RuntimeException('文件保存失败:'.$this->filePath);
        }
        return \filesize($this->filePath);
    }

    /**
     * @param int $timeout
     * @return string|null
     * @throws \Exception
     */
    public function getBody(int $timeout) : ? string
    {
        $client = new HttpClient($this->url, $timeout);
        $response = $client->send();
        $headers = $response->getHeaders();
        if ($headers['http_code'] === 200) {
            return $response->getBody();
        }
        return null;
    }

    public function setUrl(string $url) : void
    {
        $this->url = $url;
    }

    public function getDuration():float {
        return $this->duration;
    }

    public function getUrl() : string {
        return $this->url;
    }

    public function getFilePath():string {
        return $this->filePath;
    }

    public function getFileSize():int {
        if($this->exists()) {
            return \filesize($this->filePath);
        }
        return 0;
    }

    public function filename() : string
    {
        return $this->filename;
    }

    public function delete() :bool
    {
        return $this->exists() && unlink($this->filePath);
    }

    public function exists() :bool
    {
        \clearstatcache();
        return file_exists($this->filePath);
    }
}
