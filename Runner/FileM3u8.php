<?php
declare(strict_types=1);

namespace Downloader\Runner;

use Dariuszp\CliProgressBar;
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
    /**
     * 下载失败
     * @var int
     */
    public const STATE_FAIL = 2;

    /**
     * 下载成功
     * @var int
     */
    public const STATE_SUCCESS = 10;

    /**
     * 请求文件阶段
     * @var int
     */
    public const STATE_REQUESTING_FILE = 3;

    /**
     * 请求文件成功
     * @var int
     */
    public const STATE_REQUEST_SUCCESS = 5;

    /**
     * 获取成功
     * @var int
     */
    public const STATE_CONTENT_SUCCESS = 4;

    /**
     * 等待请求
     * @var int
     */
    public const STATE_REQUEST_WAIT = 6;

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
     * 文件名称
     * @var string $filename
     */
    protected string $filename;

    /**
     * 文件后缀
     * @var string $suffix
     */
    protected string $suffix;

    /**
     * 保存到指定位置
     * @var string $absolutePath
     */
    protected string $absolutePath;

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
     * Cli 版本进度条
     * @var CliProgressBar
     */
    public CliProgressBar $cliProgressBar;

    /**
     * 文件当前状态
     * @var int $state
     */
    protected int $state = self::STATE_REQUEST_WAIT;

    /**
     * @var array $transportStreamArray
     */
    protected array $transportStreamArray = [];

    /**
     * 自定义解密
     * @var ?\Closure $callDecrypt
     */
    protected ?\Closure $callDecrypt = null;

    /**
     * json 文件行信息
     * @var array $file
     */
    protected array $file = [];

    /**
     * FileM3u8 constructor.
     * @param string $url
     * @param string $absolutePath
     * @throws \Exception
     */
    public function __construct(string $url, string $absolutePath)
    {
        $this->url = $url;
        // /home
        $this->absolutePath = $absolutePath;
        // 绘制命令行进度条
        $this->cliProgressBar = new CliProgressBar(100, 0);
    }

    /**
     * @param string $filename
     * @param string $suffix
     * @param int $permissions
     * @throws \Exception
     */
    public function saveAs(string $filename, string $suffix, $permissions = 0777): void
    {
        // 文件全路径 /home/1.mp4
        $this->filename = $filename;
        $this->suffix = $suffix;
        $this->filepath = rtrim($this->absolutePath, '\/') . DIRECTORY_SEPARATOR . "{$filename}.{$suffix}";
        $this->mkdir($permissions);
    }

    /**
     * 获取文件路径
     * @return string
     */
    public function getFilePath(): string
    {
        if (empty($this->filepath)) {
            throw new \BadMethodCallException("文件另存为路径不存在.");
        }
        return $this->filepath;
    }

    /**
     * 创建存储目录
     * @param int $permissions
     * @return string
     * @throws \Exception
     */
    public function mkdir($permissions = 0777): string
    {
        if (is_dir($this->absolutePath)) {
            return $this->absolutePath;
        }
        if (!mkdir($this->absolutePath, $permissions, true)) {
            throw new \Exception('目录创建失败：' . $this->absolutePath);
        }
        return $this->absolutePath;
    }

    public function setState(int $state): void
    {
        $this->state($state);
    }

    /**
     * 加载文件行
     * @param array $file
     * @return void
     */
    public function loadJsonFile(array $file): void
    {
        $fields = [
            'filename' => '',
            'm3u8_url' => '',
            'url_prefix' => '',
            'suffix' => 'mp4',
            'key' => '',
            'method' => '',
            'put_path' => '',
        ];
        foreach ($file as $field => $value) {
            if (!array_key_exists($field, $fields)) {
                throw new \InvalidArgumentException('json key 无效:' . $field);
            }
        }
        $this->file = $file;
    }

    /**
     * 获取当前文件内容
     * @param string $key
     * @return string
     */
    public function getJsonFile(string $key) :string {
        return $this->file[$key] ?? '';
    }

    public function setDecryptCall(\Closure $callDecrypt) : void {
        $this->callDecrypt = $callDecrypt;
    }

    /**
     * 用户自定义解密
     * @param $data
     * @param $key
     * @param $method
     * @return string
     */
    public function jsonDecrypt($data, $key, $method): string
    {
        if (!$this->callDecrypt) {
            throw new \BadMethodCallException(__METHOD__.' 错误方法调用.');
        }
        $callDecrypt = $this->callDecrypt;

        return $callDecrypt($data, $key,$method);
    }


    public function getStateText() : string
    {
        switch ($this->state) {
            case self::STATE_FAIL:
                return '失败';
            case self::STATE_REQUESTING_FILE:
                return '请求文件中';
            case self::STATE_REQUEST_SUCCESS:
                return '请求文件完成';
            case self::STATE_CONTENT_SUCCESS:
                return '加载文件完成';
            case self::STATE_REQUEST_WAIT:
                return '等待文件请求';
            case self::STATE_SUCCESS:
                return '下载完成';
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

    protected function state(int $state) : void {
        switch ($state) {
            case self::STATE_FAIL: // 发生异常，失败
            case self::STATE_REQUEST_WAIT: // 待请求状态
            case self::STATE_REQUESTING_FILE: // 正在进行网络请求
            case self::STATE_REQUEST_SUCCESS: // 网络请求成功
            case self::STATE_CONTENT_SUCCESS:  // 加载完成
            case self::STATE_SUCCESS:  // 加载完成
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
        // 标记当前状态
        $this->setState(self::STATE_REQUESTING_FILE);

        if (Downloader::isModel(Downloader::MODE_JSON) && empty($this->file)) {
            throw new \InvalidArgumentException('m3u8 文件为空');
        }
        /**
         * 文件结构信息
         * @var FileInfoM3u8 $fileInfo
         */
        $fileInfo = FileInfoM3u8::parse($raw_data = $this->getRawData());
        /**
         * 时间集合
         * @var array $timeArray
         */
        $timeArray = $fileInfo->getTimeArray();

        /**
         * @var $file TransportStreamFile
         */
        foreach ($fileArray = $fileInfo->getPathArray() as $idx => $tsPath) {
            $duration = floatval($timeArray[$idx]); // 每片段时间
            $filename = pathinfo($tsPath, PATHINFO_FILENAME); // 文件名称
            $fileUrl  = $tsPath;    // 如果是完整地址
            $file     = new TransportStreamFile($fileUrl, $filename, $duration, $this->absolutePath);
            $file->setFileM3u8($this);

            // 读取json 配置文件模式
            if(Downloader::isModel(Downloader::MODE_JSON)) {
                // https://baidu.com/11.ts
                $fileUrl  = rtrim($this->file['url_prefix'], '\/')
                    .DIRECTORY_SEPARATOR. $file->getUrl();
            } elseif  (is_a(static::class,
                GenerateUrlInterface::class,true)
            ) {
                $fileUrl  = static::generateUrl($file);
            }
            $file->setUrl($fileUrl);
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

    /**
     * 下载网络流量大小
     * @return int
     */
    public function getFileSize() : int {
        return $this->fileSizeBytes;
    }

    public function getRawData() : string
    {
        try
        {
            $client = new HttpClient($this->url);
            $response = $client->send();
        } catch (\Exception $e) {
            throw new \RuntimeException("获取原始数据失败:{$this->url},{$e->getMessage()}", 101);
        }
        $this->setState(self::STATE_REQUEST_SUCCESS);
        return $response->getBody();
    }

    public function getFilename() : string {
        return $this->filename;
    }

    public function isEncryptFile():bool {
        if (is_a(static::class, DecryptFileInterface::class,true)) {
            return true;
        } elseif (array_key_exists('key', $this->file) &&
            array_key_exists('method', $this->file))
        {
            return true;
        }
        return false;
    }

    protected function isEmpty() :bool
    {
        return empty($this->transportStreamArray);
    }

    public function exists() :bool
    {
        \clearstatcache();
        return \file_exists($this->filepath);
    }

    public function getFileSizeFormat(): string
    {
        // 读取网络文件大小
        $fileSize = $this->getFileSize();
//        $fileSize = $this->getLocalFileSize();
        // 本地文件下载完成，从磁盘读取文件大小
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
     * 已下载视屏文件大小
     * @return int
     */
    public function getLocalFileSize() : int
    {
        if ($this->exists()) {
            return \filesize($this->filepath);
        }
        return 0;
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