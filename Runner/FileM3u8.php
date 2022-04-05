<?php
declare(strict_types=1);

namespace Downloader\Runner;

use Downloader\Runner\Contracts\DecryptFileInterface;
use Downloader\Runner\Contracts\GenerateUrlInterface;

/**
 * Class M3u8File
 * @package Downloader\Runner
 * @property string $secretKey;
 * @property string $method;
 * @property ?string $directory;
 * @property string $url;
 * @property int $fileSize;
 * @property string $duration;
 *
 */
abstract class FileM3u8 implements \Iterator,\Countable
{
    use Delimiter;

    /**
     * 下载成功
     * @var int
     */
    public const STATE_SUCCESS = 1;

    /**
     * 下载失败
     * @var int
     */
    public const STATE_FAIL = 2;

    /**
     * 初始化
     * @var int
     */
    public const STATE_INIT  = 3;

    /**
     * 获取成功
     * @var int
     */
    public const STATE_CONTENT_SUCCESS  = 4;

    /**
     * @var string $url
     */
    protected string $url;

    /**
     * 消息
     * @var string $message
     */
    protected string $message = '';

    /**
     * 视频播放时长
     * @var float $second
     */
    protected float $second = 0.0;

    /**
     * @var string $filename
     */
    protected string $filename;

    /**
     * 保存到指定位置
     * @var string $directory
     */
    protected string $directory;

    /**
     * 字节大小
     * @var int $fileSizeBytes
     */
    protected int $fileSizeBytes = 0;

    /**
     * 文件全路径
     * @var string $filepath
     */
    protected string $filepath;

    /**
     * 当前长度
     * @var int $currentLength
     */
    protected int $currentLength = 0;

    /**
     * 文件当前状态
     * @var int $state
     */
    protected int $state = self::STATE_INIT;

    /**
     * 视频是否加密
     * @var bool $isEncrypt
     */
    protected bool $isEncrypt = false;

    /**
     * @var array $transportStreamArray
     */
    protected array $transportStreamArray = [];

    /**
     * FileM3u8 constructor.
     * @param string $url
     * @param string $absolutePath
     * @param $filename
     * @param $suffix
     * @throws \Exception
     */
    public function __construct(string $url, string $absolutePath, string $filename, string $suffix)
    {
        $this->url = $url;
        // /home
        $this->directory = $this->mkdir($absolutePath);
        // 1.mp4
        $this->filename  = $filename;
        // 文件全路径 /home/1.mp4
        $this->filepath  = self::delimiter("{$this->directory}/{$this->filename}.{$suffix}");
        $this->isEncrypt = is_a(static::class, DecryptFileInterface::class,true);
//        $this->transportStreamArray = $this->transportStreamArray();
//        $this->second    = $this->getPlaySecond();
    }

    /**
     * @param string $directory
     * @return string|null
     * @throws \Exception
     */
    protected function mkdir(string $directory): ?string
    {
        if ($dir_exists = is_dir($directory)) {
            return $directory;
        }
        // /home/hello/world
        if (!mkdir($directory, 0777, true)) {
            throw new \Exception('目录创建失败：' . $directory);
        }
        return $directory;
    }

    public function setState(int $state) : void
    {
        $this->state($state);
    }

    public function getStateText() : string
    {
        switch ($this->state) {
            case self::STATE_FAIL:
                return '失败';
            case self::STATE_INIT:
                return '初始化';
            case self::STATE_SUCCESS:
                return '成功';
        }
        return '未定义';
    }

    public function getState() : int
    {
        return $this->state;
    }

    public function setMessage(string $message): void {
        $this->message = $message;
    }

    public function getMessage() : string
    {
        return $this->message;
    }

    public function setCurrentProgress(int $length) :void {
        if ($length < 1) {
            $length = 0;
        }
        $this->currentLength += $length;
    }

    public function getCurrentProgress(): int {
        return $this->currentLength;
    }

    protected function state(int $state) : void {
        switch ($state) {
            case self::STATE_FAIL:
            case self::STATE_INIT:
            case self::STATE_SUCCESS:
            case self::STATE_CONTENT_SUCCESS:
                 $this->state = $state;
               return;
        }
        throw new \InvalidArgumentException('文件状态无效:'.$state);
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function transportStreamArray() : array
    {
        $files = [];
        // 文件结构信息
        $fileInfo = FileInfoM3u8::parse($raw_data = $this->getRawData());
        // 时间集合
        $timeArray = $fileInfo->getTimeArray();

        /**
         * @var $file TransportStreamFile
         */
        foreach ($fileArray = $fileInfo->getPathArray() as $idx => $tsPath) {
            $duration = floatval($timeArray[$idx]); // 每片段时间
            $filename = pathinfo($tsPath, PATHINFO_FILENAME); // 文件名称
            $fileUrl  = $tsPath;    // 如果是完整地址
            $file     = new TransportStreamFile($fileUrl, $filename, $duration, $this->directory);
            $file->setFileM3u8($this);
            if (is_a(static::class, GenerateUrlInterface::class,true)) {
                $fileUrl  = static::generateUrl($file);
                $file->setUrl($fileUrl);
            }
            $files[]  = $file;
        }

        return $files;
    }

    /**
     * 文件
     * @param array $files
     */
    public function setTransportStreamFile(array  $files) : void
    {
        $result = [];
        foreach ($files as $file)
        {
            if ($file instanceof TransportStreamFile)
            {
                $result[] = $file;
            }
        }
        $this->transportStreamArray = $result;
    }

    /**
     * 播放时长
     * @param float $second
     */
    public function setPlayTime(float $second) : void {
        $this->second = $second;
    }

    public function setFileSize(int $fileSize): void {
        $this->fileSizeBytes += $fileSize;
    }

    public function getFileSize() : int {
        return $this->fileSizeBytes;
    }

    public function getRawData() : string
    {
        if (!$raw_data = \file_get_contents($this->url))
        {
            throw new \Exception('获取原始数据失败:'.$this->url, 101);
        }
        return $raw_data;
    }

    public function getFilename() : string
    {
        return $this->filename;
    }

    public function isEncryptFile():bool {
        return $this->isEncrypt;
    }

    /**
     * 拼接整个视频片段字符串
     * @param bool $overwrite
     * @return string|null
     */
    public function mergeFileUrl( bool $overwrite = false ) : ?string
    {
        $file_exists = $this->exists();
        // 文件存在，直接覆盖写入文件
        if ($file_exists && $overwrite) {
            unlink($this->filepath);
        }

        if ($file_exists || $this->isEmpty()) {
            return null;
        }

        $path = '';
        foreach ($this->transportStreamArray as $file) {
            if ($file instanceof TransportStreamFile)
            {
                // 读取已经下载的视频片段
                $filepath = $file->getFilePath();
                if ($path !== '')
                {
                    $path .= '|'.$filepath;
                    continue;
                }
                $path .= $filepath;
            }
        }
        return $path;
    }

    protected function isEmpty() :bool
    {
        return empty($this->transportStreamArray);
    }

    /**
     * 写入二进制文件,返回字节数
     * @param string|null $data
     * @param string $suffix
     * @param bool $overwrite
     * @return int
     */
    public function writeBinaryFile(?string $data = null, string $suffix = 'mp4', bool $overwrite = false) :int
    {
        $fileSize = 0;
        $file_exists = $this->exists();
        // 是否覆盖 true, 文件存在删除，然后进行写入
        if ($file_exists && $overwrite)
        {
            // 删除文件
            \unlink($this->filepath);
        }
        if (!$file_exists)
        {
            if ($this->isEmpty()) {
                return $fileSize;
            }

            if ($data) {
                return \file_put_contents($this->filepath, $data,FILE_APPEND);
            }
            try {
                $local_file = "{$this->filepath}.{$suffix}";
                $handle = fopen($local_file, "wb");
                if (is_resource($handle)) {
                   /**
                    * @var $file TransportStreamFile
                    */
                   foreach ($this->transportStreamArray as $file) {
                       if ($file instanceof TransportStreamFile)
                       {
                           // 读取已经下载的视频片段
                           $filepath = $file->getFilePath();
                           $fileData = \file_get_contents($filepath);
                           if($this->isEncryptFile()) {
                             $fileData = $this->decrypt($fileData, $this);
                           }
                           $fileSize += \fwrite($handle, $fileData , \filesize($filepath));
                           // 删除文件
                           $file->delete();
//                            $fileSize += \file_put_contents($this->filepath, $rawData = file_get_contents($filepath),FILE_APPEND);
                       }
                   }
               }
            } finally {
                if (is_resource($handle)) {
                    \fclose($handle);
                }
            }
        }
        return $fileSize;
    }

    /**
     * 文件绝对路径
     * /home/1.mp4
     * @return string
     */
    public function getFilePath() : string
    {
        return $this->filepath;
    }

    public function exists() :bool
    {
        \clearstatcache();
        return \file_exists($this->filepath);
    }

    public function getFileSizeFormat(): string
    {
        // 0 字节
        $fileSize = $this->getFileSize();
        // 减少一次读取文件
        if ($fileSize === 0 && $this->exists()) {
            $fileSize = \filesize($this->filepath);
        }
        $map = ['Bytes', 'KB', 'MB', 'GB'];
        for ($p = 0; $fileSize >= 1024 && $p < 3; $p++) {
            $fileSize /= 1024;
        }
        return sprintf("%0.2f %s", $fileSize,  $unit = $map[$p]);
    }

    /**
     * 视频文件字节数
     * @return int
     */
    public function getLocalFileSize() : int
    {
        $fileSize = 0;

        /**
         * @var $file TransportStreamFile
         */
        foreach ($this->transportStreamArray as $file)
        {
            if ($file->exists())
            {
                $fileSize += \filesize($file->getFilePath());
            }
        }

        return $fileSize;
    }

    public function getFileCount() : int
    {
        return \count($this->transportStreamArray);
    }

    /**
     * 播放时间秒
     * @return float
     */
    public function getPlaySecond() : float
    {
        $play = 0;

        /**
         * @var $file TransportStreamFile
         */
        foreach ($this->transportStreamArray as $file) {
            if ($file instanceof TransportStreamFile) {
                $play += $file->getDuration();
            }
        }

        return $play;
    }

    public function getUrl() : string
    {
        return $this->url;
    }

    /**
     * 播放时长
     * @return string
     */
    public function getPlaySecondFormat() : string
    {
        $seconds = round($this->second, 0);
        $hour = intval($seconds / 3600);
        $min = intval($seconds % 3600 / 60);
        $second = round($seconds % 3600 % 60);

        return sprintf("%s:%s:%s",
            str_pad((string)$hour, 2, '0', STR_PAD_LEFT),
            str_pad((string)$min, 2, '0', STR_PAD_LEFT),
            str_pad((string)$second, 2, '0', STR_PAD_LEFT)
        );
    }

    /**
     *
     * @return int
     */
    public function count() :int {
        return \count($this->transportStreamArray);
    }

    public function current()
    {
        return \current($this->transportStreamArray);
    }

    public function next()
    {
        \next($this->transportStreamArray);
    }

    public function key()
    {
        return \key($this->transportStreamArray);
    }

    public function valid()
    {
        return \key($this->transportStreamArray) !== null;
    }

    public function rewind()
    {
       \reset($this->transportStreamArray);
    }
}