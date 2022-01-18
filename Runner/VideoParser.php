<?php
declare(strict_types=1);

namespace Downloader\Runner;

use Downloader\Runner\Contracts\DecodeVideoInterface;
use Downloader\Runner\Contracts\HttpRequestInterface;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine;
use Swoole\Coroutine\WaitGroup;

/**
 * Class Parser
 * @package Downloader\Runner
 *
 * 20190524/c7dWMD0m/800kb/hls/index.m3u8
 */
abstract class VideoParser
{
    /**
     * @var array $m3u8Files
     */
    protected static array $m3u8Files = [];

    /**
     * @var WaitGroup $wg
     */
    protected WaitGroup $wg;

    /**
     * @var HttpRequest $httpClient
     */
    protected HttpRequest $httpClient;

    /**
     * @var LoggerInterface $logger
     */
    protected LoggerInterface $logger;

    /**
     * VideoParser constructor.
     * @throws \Exception
     */
    public function __construct()
    {
        $this->wg = new WaitGroup();
        $this->httpClient = Downloader::getContainer(HttpRequestInterface::class);
        $this->logger = Downloader::getContainer(LoggerInterface::class);
    }

    /**
     * @param array $taskUrls
     * @return array
     * @throws DownloaderException
     */
    public function load(array $taskUrls)
    {
        $this->logger->debug(
            sprintf(
                "=======>%s 初始化到内存中, M3U8 文件已加载到内存[%d]个<=======",
                static::class, count($taskUrls)
            )
        );
        foreach ($taskUrls as $m3u8_url) {
            $this->wg->add();
            Coroutine::create([$this, 'downloadM3u8Objects'], $m3u8_url);
        }

        if ($this->wg->wait(300) === false) {
            throw DownloaderException::timeout(
                sprintf("The download file timeout is 300 seconds, { %s }", implode($taskUrls, ','))
            );
        }
        $this->logger->debug(
            sprintf(
            "=======>%s 初始化到内存完成, M3U8 文件已加载到内存[%d]个<=======",
            static::class, count(static::$m3u8Files)
            )
        );
        return static::$m3u8Files;
    }

    /**
     * @param string $m3u8Url
     * @throws \Exception
     */
    public function downloadM3u8Objects(string $m3u8Url): void
    {
        Coroutine::defer(fn () => $this->wg->done());

        try {
            $response = $this->httpClient->send($m3u8Url);
            // 加载文件失败
            if (empty($response) || empty($response->getData())) {
                return;
            }
            $fileInfo = FileInfoM3u8::parser($response->getData());
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return;
        }

        $m3u8File = new FileM3u8($response->getHeaders(), $fileInfo, $filename = static::filename($m3u8Url));
        $m3u8File->addM3u8Url($m3u8Url);

        // 文件ts
        $fileTsList = $fileInfo->getPathTs();
        // 播放时间
        $playTime = $fileInfo->getTimes();
        // 保存目录
        $tsPutDir = $m3u8File->getPutFileDir();
        foreach ($fileTsList as $id => $pathTs) {
            $time = $playTime[$id];
            $m3u8File->addPartTs(static::newPartTs($tsPutDir, (float)$time, $m3u8Url, $pathTs));
        }

        // 设置加密
        if ($m3u8File->isEncrypt() && is_a(static::class, DecodeVideoInterface::class, true)) {
            $key = static::key($m3u8File);
            $keyMethod = static::method($m3u8File);
            $m3u8File->setKey($key);
            $m3u8File->setEncryptKey($keyMethod);
        }

        static::$m3u8Files[$m3u8File->getFilename()] = $m3u8File;
    }

    /**
     * 创建分片对象
     * @param string $putFileDir
     * @param float $playTime
     * @param string $m3u8FileUrl
     * @param string $pathTs
     * @return PartTs
     */
    protected function newPartTs(string $putFileDir, float $playTime, string $m3u8FileUrl, string $pathTs)
    {
        $remote_ts_url = $this->tsUrl($m3u8FileUrl, trim(ltrim($pathTs, ',')));
        return new PartTs($putFileDir, $playTime, $remote_ts_url);
    }

    /**
     * 下载文件命名
     * @param string $m3u8Url
     * @return string
     */
    abstract protected function filename(string $m3u8Url): string;

    /**
     * 获取完整ts 地址
     * @param string $m3u8FileUrl
     * @param string $partTsUrl
     * @return string
     */
    abstract public function tsUrl(string $m3u8FileUrl, string $partTsUrl): string;
}
