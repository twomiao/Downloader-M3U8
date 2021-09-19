<?php
declare(strict_types=1);

namespace Downloader\Runner;
/*
 * #EXTM3U //必需，表示一个扩展的m3u文件
 * #EXT-X-VERSION:3 //hls的协议版本号，暗示媒体流的兼容性
 * #EXT-X-MEDIA-SEQUENCE:xx //首个分段的sequence number
 * #EXT-X-ALLOW-CACHE:NO //是否缓存
 * #EXT-X-TARGETDURATION:5 //每个视频分段最大的时长（单位秒）
 * #EXT-X-DISCONTINUITY //表示换编码
 * #EXTINF: //每个切片的时长
*/

class FileM3u8
{
    // 解析器类
    public static string $parserClass;
    // 解密密码和解密算法
    public static string $decryptKey;
    public static string $decryptMethod;

    // 下载视频总数量
    public static int $m3utFileCount = 0;

    // 响应头
    protected int $bodySize = 0;
    protected string $mimeType;
    protected string $etag;
    protected ?\DateTimeInterface $lastModified = null;
    protected string $statusCode = '304';

    // 播放总时长
    protected float $playTotalTime;
    public array $filesTs = [];
    protected string $filename;

    protected string $putFileDir;
    protected string $putFile;

    // m3u8 文件结构
    protected string $key;
    protected string $method;
    protected float $maxDur;
    protected string $version;
    protected string $m3u8Url;

    /**
     * 解密密码
     * @var string $keyPassword
     */
    protected string $keyPassword;

    /**
     * 匿名响应对象
     * @var $respHeader
     */
    private $respHeader;

    /**
     * FileM3u8 constructor.
     * @param $respHeader
     * @param $fileInfoM3u8 FileInfoM3u8
     * @param string $fileName
     * @param string $suffix
     * @param string $keyPassword
     * @throws \Exception
     */
    public function __construct($respHeader, $fileInfoM3u8, string $keyPassword, string $fileName, string $suffix = 'mp4')
    {
        $this->respHeader = $respHeader;
        static::$m3utFileCount++;
        $this->filename = $fileName;
        // /ROOT_DIRECTORY/c7dWMD0m/
        $this->putFileDir = DOWNLOAD_DIR . '/' . $fileName;
        // 20210903/c7dWMD0m/
        $this->putFile = sprintf("%s/%s.%s", $this->putFileDir, $fileName, $suffix);

        if (!is_dir($this->putFileDir)) {
            mkdir($this->putFileDir, 0777, true);
        }

        // m3u8
        $this->keyPassword = $keyPassword;
        $this->method = $fileInfoM3u8->getMethodKey() ?: 'aes-128-cbc';
        $this->maxDur = $fileInfoM3u8->getMaxTime();
        $this->playTotalTime = self::getPlayTimes($fileInfoM3u8->getTimes());

        // resp header
        $this->statusCode = $respHeader['status_code'];
        $this->etag = $respHeader['etag'] ?? '';
        $this->mimeType = $respHeader['content-type'];
        $this->bodySize = (int)($respHeader['content-length'] ?? 0);
        if (isset($respHeader['last-modified'])) {
            $this->lastModified = new \DateTime($respHeader['last-modified']);
        }
    }

    public function exists(): bool
    {
        return file_exists($this->putFile);
    }

    /**
     * 解密密码
     *
     * @return string
     */
    public function getDecryptKey(): string
    {
        return $this->keyPassword;
    }

    /**
     * 加密方式
     *
     * @return string
     */
    public function getDecryptMethod(): string
    {
        return $this->method;
    }

    /**
     * @param array $times
     * @return float|int
     */
    private static function getPlayTimes(array $times)
    {
        $totalTime = 0;

        foreach ($times as $time) {
            $curr = (float)sprintf("%0.2f", $time);
            $totalTime += $curr;
        }
        return $totalTime;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function addPartTs(PartTs $partTs): void
    {
        $this->filesTs[] = $partTs;
    }

    public function tsCount(): int
    {
        if (empty($this->filesTs)) {
            throw new FileException('Fragment is empty.');
        }

        return \count($this->filesTs);
    }

    public function getPlayTime(string $format = 'seconds')
    {
        $format = strtoupper($format);
        switch ($format) {
            case 'MINUTE':
            case 'MIN':
                return sprintf("%0.2f", $this->playTotalTime / 60);
        }
        return $this->playTotalTime;
    }

    public function getPutFileDir()
    {
        return realpath($this->putFileDir);
    }

    /**
     * M3u8 完整文件字节大小
     * @return string
     * @throws FileException
     * @var string $format
     */
    public function getFileSize(string $format = 'MB'): string
    {
        if (!file_exists($this->putFile)) {
            throw new FileException("{$this->putFile} file does not exist.");
        }

        $fileSize = (int)filesize($this->putFile);

        $format = strtoupper($format);
        switch ($format) {
            case 'GB':
                $fileSize = $fileSize / 1024 / 1024 / 1024;
                break;
            case 'MB':
                $fileSize = $fileSize / 1024 / 1024;
                break;
            case 'KB':
                $fileSize = $fileSize / 1024;
                break;
            default:
                $format = "Bytes";
                break;
        }

        return sprintf("%0.2f %s", $fileSize, $format);
    }

    public function putFile()
    {
        if (empty($this->filesTs)) {
            throw new \LogicException('Object is empty.');
        }

        if ($this->exists()) {
            return;
        }

        foreach ($this->filesTs as $filename => $tsFile) {
            if ($tsFile instanceof PartTs) {
                if ($tsFile->exists()) {
                    $data = \file_get_contents($tsFile->getPutFile());
                    \file_put_contents($this->putFile, $data, FILE_APPEND);
                    $tsFile->delete();
                }
            }
        }
    }

    public function addM3u8Url(string $m3u8Url): void
    {
        $this->m3u8Url = $m3u8Url;
    }

    public function getM3u8Url(): string
    {
        if (empty($this->m3u8Url)) {
            throw new FileException('M3u8 url not found.');
        }

        return $this->m3u8Url;
    }

    public function __toString()
    {
        return $this->m3u8Url;
    }
}