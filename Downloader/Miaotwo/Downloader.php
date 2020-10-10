<?php

namespace Downloader\Miaotwo;

use Swoole\Coroutine\Channel;
use Swoole\Coroutine;

class Downloader
{
    /**
     * 版本号
     */
    const VERSION = '1.0';

    /**
     * 解析器
     * @var MovieParser
     */
    protected $movieParser;

    /**
     * 并发下载速度
     * @var int
     */
    protected $concurrent = 5;

    /**
     * 已下载文件大小
     * @var int
     */
    protected $downoadedSize = 0;

    /**
     * 已成功下载文件数量
     * @var int
     */
    protected $downloadeCount = 0;

    /**
     * 下载成功的队列
     * @var array
     */
    protected $tsQueue = [];

    /**
     * 保存到本地电脑
     * @var
     */
    protected $exportToLocal;

    /**
     * 每秒下载大小
     * @var int
     */
    private $downloadSecondSizeTemp = 0;

    /**
     * 开始下载时间
     * @var int
     */
    private $downloadTimeTemp = 0;


    /**
     * 总文件数量
     * @var int
     */
    private $downloadedTotalCount = 0;

    /**
     * Downloader constructor.
     * @param MovieParser $movieParser 解析器
     * @param string $exportToLocal 视频保存路径
     */
    public function __construct(MovieParser $movieParser, string $exportToLocal = '')
    {
        $this->movieParser = $movieParser;
        $this->exportToLocal = trim($exportToLocal);
        if (!is_dir($this->exportToLocal) &&
            !mkdir($this->exportToLocal, 0755, true)
        ) {
            Logger::create()->error("创建本地视频目录失败：{$exportToLocal}", '[ Error ] ');
            exit(255);
        }
        echo Utils::baseInfo();
    }

    public function run()
    {
        $this->movieParser->parsed();
        $this->downloadedTotalCount = $this->movieParser->getDownloads();
        Logger::create()->info("分析M3U8地址获取到：{$this->downloadedTotalCount}个文件", '[ Found ] ');

        \Swoole\Runtime::enableCoroutine(SWOOLE_HOOK_ALL | SWOOLE_HOOK_CURL);

        \Co\run(function () {
            $channel = new Channel($this->concurrent);

            foreach ($this->movieParser->getParserTsQueue() as $number => $tsUrl) {
                $localTsPath = $this->exportToLocal . '/' . $number . '.ts';
                clearstatcache();
                if (is_file($localTsPath)) {
                    $this->tsQueue[$number] = $localTsPath;
                    $this->downloadeCount = ++$this->downloadeCount;
                    Logger::create()->warn("文件序号#{$number}：{$localTsPath}", '[ Pass ] ');
                    continue;
                }

                $channel->push(true);
                go(function () use ($channel, $tsUrl, $number, $localTsPath) {

                    defer(function () use ($channel) {
                        $channel->pop();
                    });

                    // speed/s
                    $this->downloadTimeTemp = time();
                    $reries = 0;
                    $this->downloadeCount = ++$this->downloadeCount;
                    do {
//                        Logger::create()->debug("发现视频文件序号#{$number}：{$tsUrl}", '[ Found ] ');
                        $content = HttpClient::get($tsUrl);
                        $downloadedSize = strlen($content);

                        ++$reries;
                        if ($downloadedSize < 1024) {
                            Logger::create()->warn("重试({$reries})次数, 网络地址：{$tsUrl}", "[ Retry ] ");
                        }
                    } while ($reries < 3 || $downloadedSize < 1024); // 重试3次

                    if ($downloadedSize > 1024) {
                        $fileSize = file_put_contents($localTsPath, $content, FILE_APPEND);
                        $this->tsQueue[$number] = $localTsPath;
                        $this->downoadedSize += $fileSize;
                        // 每秒下载速度
                        $this->downloadSecondSizeTemp += $fileSize;
                        if (($timeNow = (time() - $this->downloadTimeTemp)) >= 1) {
                            $downloadSpeed = Utils::downloadSpeed($timeNow, $this->downloadSecondSizeTemp);
                            if ($downloadSpeed > 0) {
                                ProgressBar::darw($this->downloadeCount, $this->downloadedTotalCount, $downloadSpeed);
                            }

                            $this->downloadTimeTemp = 0;
                            $this->downloadSecondSizeTemp = 0;
                        }
//                    Logger::create()->info("保存视频文件序号#{$number}：{$localTsPath}", '[ Saved ] ');
                    }
                });
            }

            for ($concurrent = $this->concurrent; $concurrent--;) {
                $channel->push(true);
            }
        });

        $this->checkDownloadError();

        (new ProgressBar($this))->paint();
    }

    // 文件下载失败不会创建新的MP4文件，视为任务失败
    private function checkDownloadError()
    {
        $downloads = $this->movieParser->getDownloads();

        if (($this->downloadeCount - $downloads) < 0) {
            Logger::create()->error("任务生成失败, 文件成功数量统计: {$downloads}/{$this->downloadeCount}", "[ Error ] ");
            exit(255);
        }
    }

    /**
     * 并发数设置
     * @param int $concurrent
     * @return $this
     */
    public function maxConcurrent(int $concurrent = 5)
    {
        $this->concurrent = $concurrent < 1 ? $concurrent : $concurrent;
        return $this;
    }

    /**
     * 下载视频保存到本地电脑
     * @return string
     */
    public function getExportPath()
    {
        return $this->exportToLocal;
    }

    /**
     * @return int
     */
    public function getDownoadedSize(): int
    {
        return $this->downoadedSize;
    }

    /**
     * 下载成功的队列
     * @return array
     */
    public function getQueue()
    {
        return $this->tsQueue;
    }

    /**
     * @return int
     */
    public function getDownloadeCount(): int
    {
        return $this->downloadeCount;
    }

    /**
     * 队列长度
     * @return int
     */
    public function getQueueLength()
    {
        return count($this->tsQueue);
    }
}

