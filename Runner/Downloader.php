<?php

declare(strict_types=1);

namespace Downloader\Runner;

use Dariuszp\CliProgressBar;
use Exception;
use RuntimeException;
use SplObjectStorage;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Swoole\Coroutine\WaitGroup;
use Symfony\Component\Console\Helper\Table;
use Swoole\Coroutine\Barrier;
use Swoole\Timer;
use Swoole\Coroutine\System;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

/**
 * @version 3.0
 * Class Downloader
 * @package Downloader\Runner
 */
class Downloader
{
    // 版本号
    public const VERSION = '3.0';

    // 命令行程序名称
    public const PROGRAM_NAME = 'Downloader-M3u8';

    // 正在运行
    protected const STATUS_RUNNING = 2;

    // 正在启动
    protected const STATUS_STARTING = 1;
    
    // 正在停止
    protected const STATUS_STOPPING = 3;
    
    // 停止完成
    protected const STATUS_STOPPED = 4;

    /**
     * 当前运行状态
     * @property int $stateCurrent
     */
    protected static int $status = self::STATUS_STARTING;

    /**
     * 等待下载文件对象
     * @property array $waitingToDownloadFiles
     */
    protected static ?\SplObjectStorage $waitingToDownloadFiles = null;

    /**
     * 启动时间
     * @property int $start
     */
    protected static int $startup;

    /**
     * @property ConfigFile $configFile
     */
    protected static ConfigFile $configFile;

    /**
     * 文件保存路径
     * @property string $savePath
     */
    public static string $savePath;

    /**
     *
     * @property $dispatchFileSliceToQueue \Swoole\Coroutine\Channel
     */
    protected Channel $dispatchFileSliceToQueue;

    /**
     * 视频存放路径
     * @property string $savePath
     */
    public function __construct(string $savePath)
    {
        // 启动时间
        static::$startup = time();
        static::$configFile = Container::make("config");
        static::$savePath = $savePath;
        static::$waitingToDownloadFiles = new \SplObjectStorage();
        $this->dispatchFileSliceToQueue = new Channel(static::$configFile->count);
    }

    public function download(FileM3u8 ...$fileM3u8): void
    {
        echo PHP_EOL;
        $tid = Prompt::loading("加载用时「%d」秒 %s");
        sleep(5);
        $barrier = Barrier::make();
        $coroutineNum = 0;
        /**
         * @var $file FileM3u8
         */
        foreach($fileM3u8 as $file) {
            // 文件对象全部保存进下载程序
            static::waitingToDownloadFiles()->attach($file, $file);
            // 本地已经下载过，跳过下载
            if ($file->isFile()) {
                $file->taskFinished->flag  = TaskFinished::FLAG_LOCAL_FILE_EXISTS;
                continue;
            }
            // 开启一个协程下载文件信息
            Coroutine::create(function () use ($barrier, $file, &$coroutineNum) {
                !is_dir($file->savePath) && mkdir($file->savePath, 0777, true);
                try {
                    /**
                     * 下载文件信息
                     * @var $file Downloader\Runner\FileM3u8;
                     */
                    $file = $this->downloadM3u8FileInfo($file);
                    $file->taskFinished->total = $file->count();
                } catch(\Exception $e) {
                    $file->taskFinished->flag = TaskFinished::FLAG_DOWNLOAD_FILEINFO_ERROR;
                    // 记录日志
                    Container::make("log")->error((string)$e);
                } finally {
                    $coroutineNum++;
                }
            });
        }
        Barrier::wait($barrier);
        // 文件信息加载完毕
        Prompt::stop($tid);
        echo PHP_EOL;
        echo PHP_EOL;
    }

    protected function downloadM3u8FileInfo(FileM3u8 $file): FileM3u8
    {
        $client = new Client($file->m3u8Url);
        /**
         * @var $reponse Response
         */
        $respnose = $client->send("get");
        if (($statusCode = $respnose->getStatusCode()) === 200) {
            $data = $respnose->getBody();
            $pathList = FileM3u8::getPathList($data);
            if (0 === \count($pathList)) {
                throw new RuntimeException("Invalid download file { {$file->m3u8Url} }.");
            }
            // $file->taskFinished->total = \count($pathList);
            $savePath = $file->savePath.DIRECTORY_SEPARATOR;
            // 得到一个列表
            $file->addFlieSlices(
                // 批量转换对象
                ...array_map(
                    static fn (
                        string $path
                    ) => (
                        new FileSlice($path, $savePath)),
                    $pathList
                )
            );
            return $file;
        }
        throw new RuntimeException("Abnormal server response status code {$statusCode}.");
    }

    protected function isRunningDownloader()
    {
        if (static::$status === static::STATUS_RUNNING) {
            throw new RuntimeException("Task is running.");
        }
    }

    public function start()
    {
        // 是否已经启动
        $this->isRunningDownloader();
        // 分发文件到Channel 进行下载
        $this->dispatchFileToQueue();
        // 监控队列开始下载分片
        $this->downloadFileSliceFromQueue();
        // 显示任务进度
        $this->taskDownloadCompleted();
        // 视频文件保存到本地
        $this->saveFileToHardDisk();
        // 转换文件格式
        $this->diskFileConvertFormat();
        // 汇报下载完成信息
        $this->displayDownloadInfo();
    }

    /**
     * 无返回值
     * 显示下载结果
     * 文件名称 文件大小 文件保存路径 文件分片总数量
     */
    protected function displayDownloadInfo(): void
    {
        if(static::runStatus() !== static::STATUS_RUNNING)
        {
            return;
        } 
        static::$status = static::STATUS_STOPPED;
        $out = Container::make(OutputInterface::class);
        $table = new Table($out);
        $table->setHeaderTitle("www.1905.com");
        $table->setVertical(true);
        $table
            ->setHeaders(['序号', '文件名称', '文件类型', '文件大小', '下载保存路径', '需下载分片', '已下载分片','错误分片', '下载状态'])
            ->setRows($this->downloadFileInfo());
        $table->setStyle("box");
        $table->render();
        posix_kill(getmypid(), SIGINT);
    }

    protected function downloadFileInfo(): array
    {
        $res = [];
        foreach (static::waitingToDownloadFiles() as $k => $file) {
            $fileSize = $file->getSizeformat();
            $filename = $file->getBasename();
            // $status = $file->taskFinished->flag;
            $downloadPath   = $file->getFilename();
            $fileSliceCount = $file->count();
            $mimeType  = $file->getMimeType();
            $res[] = array(
                $k++,
                "<info>『{$filename}』</info>",
                $mimeType,
                $fileSize,
                $downloadPath,
                $fileSliceCount,
                $file->taskFinished->succeedNum,
                $file->taskFinished->errors,
                $file->taskFinished->flag === TaskFinished::FLAG_SAVE_FILE_SUCCEED ?
                 "<info>『成功』</info>":"<error>『失败』</error>"
            );
        }
        return $res;
    }

    /**
     * 无返回值
     * 使用多进程加速视频格式转换
     */
    protected function diskFileConvertFormat(): void
    {
        // 终止进程运行
        if (static::runStatus() === static::STATUS_STOPPING) {
            static::$status = static::STATUS_STOPPED;
        }
        if (static::runStatus() === static::STATUS_STOPPED)
        {
            posix_kill(getmypid(), SIGINT);
            return;
        }
    
        Container::make("event")->dispatch(new FFmpegConvertVideoFormat(static::waitingToDownloadFiles()), FFmpegConvertVideoFormat::NAME);
    }

    protected function newMutliProgressBar(array $files): ?MultipleProgressBar
    {
        $max = 0;
        foreach ($files as $file) {
            $len = mb_strwidth($file->getBasename(".mp4"));
            if ($max < $len) {
                $max = $len;
            }
        }

        $multipleProgressBar = new MultipleProgressBar($files);

        foreach($files as $file) {
            // 每个文件对应一条进度条
            $basename = $file->getBasename(".mp4");
            $basename = str_repeat(" ", $max - mb_strwidth($basename))."『{$basename}』";
            //『』︽︾「」;
            $progressBar = new CliProgressBar($file->count());
            $progressBar->setDetails("{$basename}» ");
            $progressBar->setColorToCyan();
            $multipleProgressBar->addProgressBar($file, $progressBar);
        }
        $multipleProgressBar->display();
        return $multipleProgressBar;
    }

    protected static function downloadedSuccessfullyFiles(SplObjectStorage $files): array
    {
        $result = [];
        foreach($files as $file) {
            if ($file->taskFinished->flag === TaskFinished::FLAG_DOWNLOAD_SUCCEED) {
                $result[] = $file;
            }
        }
        return $result;
    }

    protected static function runStatus() : int
    {
        return static::$status;
    }

    protected function saveFileToHardDisk(): void
    {
        if (static::runStatus() !== static::STATUS_RUNNING)
        {
            return;
        }
        // 下载失败
        $files = $this->downloadedSuccessfullyFiles(static::waitingToDownloadFiles());
        if(\count($files) === 0) {
            return;
        }
        warning(sprintf("%s 开始生成视频文件......", date('Y-m-d H:i:s')));
        // 创建多进度条
        $multiProgressBar = $this->newMutliProgressBar($files);

        $wg = new WaitGroup();
        $wg->add();
        $tid = \Swoole\Timer::tick(50, function () use ($wg, $multiProgressBar, &$tid) {
            $num = $multiProgressBar->drawCliMultiProgressBar();
            if ($num  === $multiProgressBar->count()) {
                Timer::clear($tid);
                info(sprintf("\n%s 保存「{$num}」个文件成功.", date('Y-m-d H:i:s')));
                $wg->done();
            }
        });

        /**
         * @var $file FileM3u8
         */
        foreach (static::waitingToDownloadFiles() as $file) {
            // 只允许下载完成的文件，并且本地不存在才进行文件合并操作
            if ($file->taskFinished->flag !== TaskFinished::FLAG_DOWNLOAD_SUCCEED) {
                continue;
            }
            // 由于有磁盘IO，咱们还是开启协程
            // 开启一个协程读写文件
            $wg->add();
            Coroutine::create(function () use ($wg, $file, &$count) {
                Coroutine::defer(fn () =>  $wg->done());
                try {
                    foreach($file->fileSlice as $fileSlice) {
                        $fileSize = $this->fileSliceAppendToFile($fileSlice);
                        if ($fileSize > 0) {
                            $file->taskFinished->succeedNum++;
                        } else {
                            $file->taskFinished->errors++;
                        }
                    }
                    $file->taskFinished->flag = TaskFinished::FLAG_SAVE_FILE_SUCCEED;
                } catch(\Exception $e) {
                    $file->taskFinished->errors++;
                    $file->taskFinished->flag = TaskFinished::FLAG_SAVE_FILE_ERROR;
                    // 记录日志文件，这里直接忽略, 因为合并很少会出现失败
                    Container::make("log")->error((string)$e);
                } finally {
                    $count++;
                }
            });
        }
        $wg->wait();
    }

    /**
     * 返回每个文件分片字节大小
     */
    protected function fileSliceAppendToFile(FileSlice $fileSlice): int
    {
        $filename = $fileSlice->belongsToFile()->tmpFilename();
        $data = System::readFile($fileSlice->filename());
        return (int)\Swoole\Coroutine\System::writeFile($filename, $data, FILE_APPEND);
    }

    protected function taskDownloadCompleted()
    {
        $taskCompleted = new \Swoole\Coroutine\Channel();
                     // 创建多进度条
        $files = static::downloadedSuccessfullyFiles(static::waitingToDownloadFiles());
        $multiProgressBar = $this->newMutliProgressBar($files);
        $tid = Timer::tick(500, function () use (&$tid, $taskCompleted,  $multiProgressBar) {
            // 多进度条显示
            match(static::$status)
            {
                static::STATUS_RUNNING => $multiProgressBar->drawCliMultiProgressBar(),
                default => ""
            };

            $dispatchQueueStats = $this->dispatchFileSliceToQueue->stats();
            // var_dump($dispatchQueueStats);
            // 消费者数量与配置文件一致,生产者数量全部退出，队列容量为空
            // 说明程序下载完成
            if ($dispatchQueueStats['consumer_num'] === static::$configFile->count &&
            $dispatchQueueStats['producer_num'] === 0 &&
            $dispatchQueueStats['queue_num'] === 0) {
                match(static::$status)
                {
                    static::STATUS_RUNNING => $multiProgressBar->drawCliMultiProgressBar(),
                    default => ""
                };
                $this->dispatchFileSliceToQueue->close();
                \Swoole\Timer::clear($tid);
                info(sprintf("%s 下载视频文件结束!", date('Y-m-d H:i:s')));
                $taskCompleted->push(true);
            }
        });
        // 任务下载是否已经完成
        $taskCompleted->pop();
        // var_dump("Download succeed!");
        // 重置当前文件下载状态
        static::resetTaskFinished();
    }

    protected static function resetTaskFinished(): void
    {
        foreach (static::waitingToDownloadFiles() as $file) {
            $file->resetTaskFinished();
        }
    }

    protected function downloadFileSliceFromQueue(): void
    {
        $count = static::$configFile->count;
        while($count -- > 0) {
            \Swoole\Coroutine::create(function () {
                while($fileSlice = $this->dispatchFileSliceToQueue->pop()) {
                    // 确认是下载的文件分片
                    if($fileSlice instanceof FileSlice) {
                        // 文件
                        $file = $fileSlice->belongsToFile();
                        $url = $file->downloadCdnUrl($fileSlice);
                        try {
                            $client = new Client($url);
                            // 下载文件分片的字节大小
                            // $fileSize = $client->downloadFile($fileSlice);
                            $client->downloadFile($fileSlice);
                            $fileSliceStatus = FileSlice::STATE_SUCCESS;
                            // 下载成功数累计
                            $file->taskFinished->succeedNum++;
                        } catch(\Exception $e) {
                            $fileSliceStatus = FileSlice::STATE_FAIL;
                            $file->taskFinished->flag = TaskFinished::FLAG_DOWNLOAD_ERROR;
                            // 错误数累计
                            $file->taskFinished->errors++;
                            // 写入日志
                            // error($e->getMessage());
                            Container::make("log")->error((string)$e);
                        }
                        // 标记文件分片下载状态
                        $fileSlice->setDownloadStatus($fileSliceStatus);
                    }
                }
            });
        }
    }

    protected function dispatchFileToQueue(): void
    {
        // 运行中
        static::$status = static::STATUS_RUNNING;
        // 注册信号
        foreach (static::waitingToDownloadFiles() as $file) {
            if ($file->taskFinished->flag === TaskFinished::FLAG_DOWNLOAD_FILEINFO_SUCCEED) {
                // 开启多个生产者协程开始下载
                \Swoole\Coroutine::create([$this, 'dispatchTaskToQueue'], $file);
            }
        }
        // stop
        \Swoole\Coroutine::create(fn() => $this->terminatedProcess());
    }

    protected function terminatedProcess() {
    //    \Swoole\Coroutine::defer(fn() => var_dump("退出程序..."));
       while(\Swoole\Coroutine\System::waitSignal(SIGINT))
       {
            if (static::runStatus() === static::STATUS_RUNNING)
            {
                static::$status = static::STATUS_STOPPING;
                warning(sprintf("\n%s 正在停止程序 ......", date('Y-m-d H:i:s')));

                while(!$this->dispatchFileSliceToQueue->isEmpty()) {
                    // 持续删除数据，一直到通道数据结构为空
                    // 此刻只需要等待定时器关闭通道唤醒协程,这时候返回false
                    $this->dispatchFileSliceToQueue->pop();
                }
            } elseif (static::runStatus() === static::STATUS_STOPPED) {
                info(sprintf("%s 停止运行成功!", date('Y-m-d H:i:s')));
                break;
            }
        }
    }

    protected function dispatchTaskToQueue(FileM3u8 $file): void
    {
        if (is_null($file->fileSlice) ||
            !$file->fileSlice instanceof \SplObjectStorage) {
            return;
        }
        // 标记下载完成，后续流程分片失败，文件重新标记失败
        // 投递前标记成功
        $file->taskFinished->flag = TaskFinished::FLAG_DOWNLOAD_SUCCEED;
        $file->fileSlice->rewind();
        while($file->fileSlice->valid()) {
            if (static::runStatus() !== static::STATUS_RUNNING)
            {
                $file->taskFinished->flag = TaskFinished::FLAG_DOWNLOAD_ERROR;
                return;
            }
            // 分发文件分片到下载协程
            $fileSlice = $file->fileSlice->current();
            // 文件分片已经存在本地
            if (!$fileSlice->isFile()) {
                $this->dispatchFileSliceToQueue->push($fileSlice);
            } else {
                $file->taskFinished->succeedNum++;
                $fileSlice->setDownloadStatus(FileSlice::STATE_SUCCESS);
            }

            $file->fileSlice->next();
        }
    }

    protected static function waitingToDownloadFiles(): \SplObjectStorage
    {
        return static::$waitingToDownloadFiles;
    }

    protected function downloadTime() : string
    {
        // 分钟数
        $timeCost = time() - static::$startup;
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
