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
     * Downloader constructor.
     * @param MovieParser $movieParser 解析器
     * @param string $exportToLocal 视频保存路径
     */
    public function __construct(MovieParser $movieParser, string $exportToLocal = '')
    {
        $this->movieParser = $movieParser;
        $this->exportToLocal = trim($exportToLocal);

        $mkdir = !is_dir($this->exportToLocal) && mkdir($this->exportToLocal, 0755, true);
        if ($mkdir === false) {
            Logger::create()->error("创建本地视频目录失败：{$exportToLocal}", '[ Error ] ');
            exit(255);
        }
        echo Utils::baseInfo();
    }

    public function run()
    {
        $this->movieParser->parsed();
        $files = $this->movieParser->getDownloads();
        Logger::create()->info("分析M3U8地址获取到：{$files}个文件", '[ Found ] ');

        \Swoole\Runtime::enableCoroutine(SWOOLE_HOOK_ALL | SWOOLE_HOOK_CURL);

        \Co\run(function () {

            $channel = new Channel($this->concurrent);

            foreach ($this->movieParser->getParserTsQueue() as $number => $tsUrl) {
                $localTsPath = $this->exportToLocal . '/' . $number . '.ts';
                clearstatcache();
                if (is_file($localTsPath)) {
                    $this->tsQueue[$number] = $localTsPath;
                    Logger::create()->warn("文件序号#{$number}：{$localTsPath}", '[ Pass ] ');
                    continue;
                }

                $channel->push(true);
                Coroutine::create(function () use ($channel, $tsUrl, $number, $localTsPath) {
                    $reries = 0;
                    while ($reries < 4) {
                        Logger::create()->debug("发现视频文件序号#{$number}：{$tsUrl}", '[ Found ] ');
                        $content = HttpClient::get($tsUrl);
                        if (strlen($content) > 1024) { // 1kb
                            file_put_contents($localTsPath, $content, FILE_APPEND);
                            $this->tsQueue[$number] = $localTsPath;
                            Logger::create()->info("保存视频文件序号#{$number}：{$localTsPath}", '[ Saved ] ');
                            break;
                        } else {
                            ++$reries;
                            if ($reries == 4) {
                                Logger::create()->error("资源读取失败, 网络地址：{$tsUrl}", "[ Error ] ");
                            } else {
                                Logger::create()->warn("重试({$reries})次数, 网络地址：{$tsUrl}", "[ retry ] ");
                            }
                        }
                    }
                    $channel->pop();
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
        $tasks = $this->getQueueLength();
        $downloads = $this->movieParser->getDownloads();

        if (($tasks - $downloads) < 0) {
            Logger::create()->error("任务生成失败, 文件成功数量统计: {$downloads}/{$tasks}", "[ Error ] ");
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
     * 下载成功的队列
     * @return array
     */
    public function getQueue()
    {
        return $this->tsQueue;
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

