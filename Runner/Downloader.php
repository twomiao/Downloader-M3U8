<?php

declare(strict_types=1);

namespace Downloader\Runner;

use RuntimeException;
use Swoole\Coroutine;
use Swoole\Timer;
use Swoole\Coroutine\Channel;
use Swoole\Coroutine\WaitGroup;
use Dariuszp\CliProgressBar;
use Exception;
use Swoole\Coroutine\System;
use Swoole\Process as SwProcess;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

class Downloader
{
    // 版本号
    public const VERSION = '3.0';

    // 命令行程序名称
    public const PROGRAM_NAME = 'Dl-m3u8';

    // 正在运行
    protected const STATUS_RUNNING = 2;

    // 正在启动
    protected const STATUS_STARTING = 1;

    // 正常退出
    protected const STATUS_NORMALEXIT = 5;

    // 正在停止
    protected const STATUS_STOPPING = 3;

    // 停止完成
    protected const STATUS_STOPPED = 4;

    /**
     * 文件对象
     * [hash => file oject, 'hash2' => file2, .....]
     * @property array $files
     */
    protected static array $files = [];

    /**
     * 启动时间
     * @property int $start
     */
    protected int $startAt;

    /**
     * 配置文件
     * @property ConfigFile $config
     */
    protected ConfigFile $config;

    /**
     * 当前运行状态
     * @property int $status
     */
    protected static int $status = self::STATUS_STARTING;

    /**
     * 等待下载文件对象
     * @property Channel $fileSliceQueue
     */
    protected static Channel $fileSliceQueue;

    /**
     * 待保存文件
     * @property Channel $saveFileChan
     */
    public static Channel $saveFileChan;

    /**
     * 文件数量
     * @property int $fileCount
     */
    public static int $fileCount = 0;

    /**
     * 域名
     * @var string $domain
     */
    public function __construct(protected string $domain)
    {
        $this->startAt = time();
        $this->config = Container::make("config");
        static::$saveFileChan = new Channel();
        static::$fileSliceQueue = new Channel();
    }

    public function download(FileM3u8 ...$fileM3u8): void
    {
        echo PHP_EOL;  
        $id = Prompt::loading("加载用时「%d」秒 %s");
        $end = new WaitGroup();
        foreach($fileM3u8 as $file) {
            // 保存下载文件
            static::$files[$file->id()] = $file;
            if ($file->isFile()) {
                $file->statistics->flag  = Statistics::SAVED;
                warning(sprintf("%s『%s』文件资源本地已存在!", date('Y-m-d H:i:s'), $file->getBasename(".mp4")));
                Downloader::deleteOneFile($file);
                continue;
            }
            //创建子目录
            if (!is_dir($file->subDirectory)) {
                @mkdir($file->subDirectory, 0777, true);
            }
            // 开始下载文件信息
            $end->add();
            Coroutine::create(static fn () => static::downloadM3u8FileInfo($file, $end));
        }
        $end->wait();
        // 文件信息加载完毕
        echo PHP_EOL;   
        echo PHP_EOL;   
        Prompt::stop($id);
    }

    protected static function deleteOneFile(FileM3u8 $file)
    {
        unset(static::$files[$file->id()]);
    }

    protected static function downloadM3u8FileInfo(FileM3u8 $file, WaitGroup $end)
    {
        Coroutine::defer(static fn () => $end->done());
        try {
            $client = new Client($file->m3u8Url);
            /**
             * @var $reponse Response
             */
            $respnose = $client->send("get");
            if (($statusCode = $respnose->getStatusCode()) === 200) {
                $data = $respnose->getBody();
                $pathList = FileM3u8::getPathList($data);
                if (0 === \count($pathList)) {
                    throw new RuntimeException("读取m3u8文件失败 { {$file->m3u8Url} } 无效文件!");
                }
                // 得到一个列表
                $file->addFlieSlices(
                    // 批量转换对象
                    ...array_map(
                        static fn (
                            string $path
                        ) => (
                            new FileSlice($path, $file)),
                        $pathList
                    )
                );
                Downloader::$fileCount++;
                return $file;
            }
            throw new RuntimeException("读取m3u8文件信息失败:{$file->m3u8Url},响应状态码：{$statusCode}");
        } catch(\Exception $e) {
            $file->statistics->flag = Statistics::DOWNLOAD_ERROR;
            warning(sprintf("%s『%s』 文件资源不存在!", date('Y-m-d H:i:s'), $file->getBasename(".mp4")));
            Downloader::deleteOneFile($file);
            // 记录日志
            Container::make("log")->error($e->getMessage());
        }
    }

    protected static function currentStateDlM3u8()
    {
        return static::$status;
    }

    protected function isRunDlM3u8()
    {
        if (static::currentStateDlM3u8() === static::STATUS_RUNNING) {
            throw new RuntimeException(Downloader::PROGRAM_NAME . " 已经运行.");
        }

        if (Downloader::$fileCount < 1) {
            throw new RuntimeException(Downloader::PROGRAM_NAME . " 运行结束!");
        }
    }

    public function start()
    {
        try {
            $this->isRunDlM3u8();
            // 分发文件到Channel 进行下载
            $this->dispatchFileSliceToQueue();
            // 监控队列开始下载分片
            $this->downloadFileSliceFromQueue();
            // 视频文件保存到本地
            $this->saveFileToHardDisk();
            // 显示任务进度
            $this->displayDownloadPercent();
        } catch (Exception $e) {
            warning(sprintf("%s %s", date('Y-m-d H:i:s'), $e->getMessage()));
        }
    }

    protected static function makeMutliProgressBar(FileM3u8 ...$files): ?MultipleProgressBar
    {
        $maxWidth  = max(array_map(static fn ($file) => mb_strwidth($file->getBasename(".mp4")), $files));

        $multipleProgressBar = new MultipleProgressBar($files);
        $multipleProgressBar->saveFileChan(static::$saveFileChan);
        foreach($files as $file) {
            // 每个文件对应一条进度条
            $basename = $file->getBasename(".mp4");
            $basename = str_repeat(" ", $maxWidth - mb_strwidth($basename)) . "『{$basename}』";
            //『』︽︾「」;
            $progressBar = new CliProgressBar($file->count());
            $progressBar->setDetails("{$basename}» ");
            $progressBar->setColorToCyan();
            $multipleProgressBar->addProgressBar($file, $progressBar);
        }
        $multipleProgressBar->initDisplay();
        return $multipleProgressBar;
    }

    protected function saveFileToHardDisk(): void
    {
        Coroutine::create(function () {
            while($file = static::$saveFileChan->pop()) {
                Coroutine::create(
                    static fn () => Container::make("event")->dispatch(new SaveFile($file), SaveFile::NAME)
                );
            }
        });
    }

    protected function displayDownloadPercent()
    {
        $dlQuit = new Channel();
        // 查找正在下载的文件
        $files = array_filter(static::$files, static fn ($file, $k) => $file->downloding(), 1);
        $progressBar = $this->makeMutliProgressBar(... $files);
        $tid = Timer::tick(250, function () use (&$tid, $dlQuit, $progressBar) {
            // 多进度条显示
            match(static::$status) {
                static::STATUS_RUNNING => $progressBar->display(),
                default => ""
            };

            $dispatchQueueStats = static::fileSliceQueue()->stats();
            // var_dump($dispatchQueueStats);
            if ($dispatchQueueStats['consumer_num'] === $this->config->count &&
                $dispatchQueueStats['producer_num'] === 0 &&
                $dispatchQueueStats['queue_num'] === 0 &&
                Downloader::$fileCount === 0
            ) {
                match(static::$status) {
                    static::STATUS_RUNNING => $progressBar->display(),
                    default => ""
                };
                static::fileSliceQueue()->close();
                static::$saveFileChan->close();
                Timer::clear($tid);
                $dlQuit->push(true);
            }
        });
        // 任务下载是否已经完成
        $dlQuit->pop();
        info("用时({$this->downloadTime()})运行结束!");
        if (static::$status === static::STATUS_RUNNING) {
            static::$status = static::STATUS_NORMALEXIT;
        } elseif (static::$status === static::STATUS_STOPPING) {
            static::$status = static::STATUS_STOPPED;
        }
        SwProcess::kill(getmypid(), SIGINT);
    }

    protected function downloadFileSliceFromQueue(): void
    {
        $count = $this->config->count;
        while($count -- > 0) {
            if (static::STATUS_STOPPED === static::$status ||
                 static::$status === static::STATUS_STOPPING) {
                return;
            }

            Coroutine::create(function () {
                while($fileSlice = static::fileSliceQueue()->pop()) {
                    // 确认是下载的文件分片
                    if($fileSlice instanceof FileSlice) {
                        $url = $fileSlice->file->fileSliceUrl($fileSlice);
                        try {
                            // 正在下载
                            $fileSlice->file->statistics->flag = Statistics::DOWNLOADING;
                            $client = new Client($url);
                            //  $fileSliceSize =  $client->downloadFile($fileSlice);
                            $client->downloadFile($fileSlice);
                            // 下载成功数累计
                            $fileSlice->file->statistics->succeedNum++;
                        } catch(\Exception $e) {
                            $fileSlice->file->statistics->flag = Statistics::DOWNLOAD_ERROR;
                            // 错误数累计
                            $fileSlice->file->statistics->errors++;
                            if ($fileSlice->file->statistics->errors === 1) {
                                Downloader::$fileCount--;
                            }
                            // 写入日志
                            Container::make("log")->error(sprintf("下载文件[%s]失败,失败原因:{$e->getMessage()}.", $url));
                        }
                    }
                }
            });
        }
    }

    protected function dispatchFileSliceToQueue(): void
    {
        static::$status = static::STATUS_RUNNING;
        foreach (static::$files as $file) {
            if ($file->statistics->flag === Statistics::WAITING) {
                // 开启多个生产者协程开始下载
                Coroutine::create(static fn () => static::dispatchTaskToQueue($file));
            }
        }
        // stop
        Coroutine::create(static fn () => static::cancelDownload());
    }

    protected static function cancelDownload()
    {
        while(System::waitSignal(SIGINT)) {
            $currentState = static::currentStateDlM3u8();
            if ($currentState === static::STATUS_RUNNING) {
                static::$status = static::STATUS_STOPPING;
                warning(sprintf("\n%s「%s」正在停止程序 ......", date('Y-m-d H:i:s'), Downloader::PROGRAM_NAME));

                while(!static::fileSliceQueue()->isEmpty()) {
                    static::fileSliceQueue()->pop();
                }
                Downloader::$fileCount = 0;
            } elseif ($currentState === static::STATUS_STOPPED) {
                info(sprintf("%s 「%s」停止运行成功!", date('Y-m-d H:i:s'), Downloader::PROGRAM_NAME));
                return;
            } elseif ($currentState === static::STATUS_NORMALEXIT) {
                return;
            }
        }
    }

    protected static function dispatchTaskToQueue(FileM3u8 $file): void
    {
        $file->statistics->flag = Statistics::DOWNLOADING;
        foreach ($file->fileSlice as $fileslice) {
            $currentState = static::currentStateDlM3u8();
            if ($currentState === static::STATUS_STOPPED ||
                  $currentState === static::STATUS_STOPPING) {
                return;
            }

            if (!$fileslice->isFile()) {
                static::fileSliceQueue()->push($fileslice);
            } else {
                $file->statistics->succeedNum++;
            }
        }
    }

    protected static function fileSliceQueue(): Channel
    {
        return static::$fileSliceQueue;
    }

    protected function downloadTime(): string
    {
        // 分钟数
        $timeCost = time() - $this->startAt;
        $unit     = '秒';
        if ($timeCost >= 60) {
            $unit     = '分钟';
            $timeCost /= 60;
        }

        if ($timeCost >= 60) { // 小时
            $timeCost /= 60;
            $unit     = '小时';
        }
        $timeCost   = \sprintf("%0.2f", $timeCost);

        return $timeCost . "{$unit}";
    }
}
