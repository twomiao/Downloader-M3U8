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
        $exportToLocal = dirname($this->exportToLocal);
        if (!is_dir($exportToLocal) &&
            !mkdir($exportToLocal, 0777, true)
        ) {
            throw new \Error("创建本地视频目录失败：{$exportToLocal}", 100);
        }

        echo Utils::baseInfo();
    }

    public function run()
    {
        $this->movieParser->parsed();
        $this->downloadedTotalCount = $this->movieParser->getDownloads();
        Logger::create()->info("分析M3U8地址获取到：{$this->downloadedTotalCount}个文件.\n", '[ Found ] ');

        \Swoole\Runtime::enableCoroutine(SWOOLE_HOOK_ALL | SWOOLE_HOOK_CURL);

        \Co\run(function () {
            $channel = new Channel($this->concurrent);

            foreach ($this->movieParser->getParserTsQueue() as $number => $tsUrl) {
                $localTsPath = dirname($this->exportToLocal) . '/' . $number . '.ts';
                clearstatcache();
                // skip
                if (is_file($localTsPath)) {
                    $this->tsQueue[$number] = $localTsPath;
                    $this->downloadeCount = ++$this->downloadeCount;
                    $this->downoadedSize += filesize($localTsPath);
                    continue;
                }

                $channel->push(true);
                go(function () use ($channel, $tsUrl, $number, $localTsPath) {

                    defer(function () use ($channel) {
                        $channel->pop();
                    });

                    $this->downloadTimeTemp = time();

                    $client = new HttpClient();
                    $response = $client
                        ->get()
                        ->request($tsUrl);

//                    file_put_contents("out.log", $this->downloadeCount . PHP_EOL, FILE_APPEND);
                    if ($response->isResponseOk() && $response->getBodySize() > 1024) {
                        $this->downloadeCount = ++$this->downloadeCount;
                        $fileSize = file_put_contents($localTsPath, $response->getBody(), FILE_APPEND);
                        $this->tsQueue[$number] = $localTsPath;
                        $this->downoadedSize += $fileSize;
                        // 每秒下载速度
                        $this->downloadSecondSizeTemp += $fileSize;
                        if (($timeNow = (time() - $this->downloadTimeTemp)) > 0) {
                            $downloadSpeed = Utils::downloadSpeed($timeNow, $this->downloadSecondSizeTemp);
//                            if ($downloadSpeed > 0) {
                            ProgressBar::darw($this->downloadeCount, $this->downloadedTotalCount, $downloadSpeed);
//                            }

                            $this->downloadTimeTemp = 0;
                            $this->downloadSecondSizeTemp = 0;
                        }
//                    Logger::create()->info("保存视频文件序号#{$number}：{$localTsPath}", '[ Saved ] ');
                    } else {
                        --$this->downloadeCount;
                        var_dump($tsUrl . ',filesize=' . $response->getBodySize());
                    }
                });
            }

            for ($concurrent = $this->concurrent; $concurrent--;) {
                $channel->push(true);
            }
            $channel->close();
        });

        $this->checkDownloadError();

        (new ProgressBar($this))->paint();
    }

    // 文件下载失败不会创建新的MP4文件，视为任务失败
    private function checkDownloadError()
    {
        $downloads = $this->movieParser->getDownloads();

        if (($this->downloadeCount - $downloads) < 0) {
            Logger::create()->error("可重新尝试下载失败任务, 文件成功数量统计: {$this->downloadeCount}/{$downloads}\n", "[ Error ] ");
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

