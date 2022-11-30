<?php declare(strict_types=1);

namespace Downloader\Runner;

use Pimple\Container;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Swoole\Coroutine\WaitGroup;
use Swoole\Process;
use Swoole\Timer;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

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
    protected const STATE_RUNNING = 2;

    // 正在启动
    protected const STATE_STARTING = 1;

    // 退出
    protected const STATE_QUIT = 3;

    /**
     * 当前运行状态
     * @var int $stateCurrent
     */
    protected static int $stateCurrent = self::STATE_STARTING;

    /**
     * @var Container $container
     */
    protected static Container $container;

    /**
     * 文件队列
     * @var array $files
     */
    protected array $files = [];

    /**
     * 启动时间
     * @var int $start
     */
    protected int $startUp;

    /**
     * @var bool $flag
     */
    protected bool $flag = false;

    /**
     * 超时时间
     * @var int $timeout
     */
    protected int $timeout = 240;

    /**
     * 命令行输出
     * @var Terminal $cmd
     */
    protected Terminal $terminal;

    /*-----------------------------------------
     | 协程池
     | ----------------------------------------
     | 并发数量       $workerCount
     | 队列大小       $queueLength
     | 任务队列       $queueMaxSizeChannel
     | 挂起全部协程    $waitGroup
     | 退出消费者协程  $hasQuit
     -----------------------------------------*/
    public int $queueLength    = 90;
    protected int $workerCount = 8;
    protected Channel $queueChannel;
    protected WaitGroup $waitGroup;
    protected Channel $quitProgram;
    protected Channel $writeFile;
    protected bool $hasQuit = false;

    /**
     * Downloader constructor.
     * @param Container $container
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    public function __construct(Container $container, InputInterface $input, OutputInterface $output)
    {
        // 协程数量
        $this->workerCount = $this->getWorkerCount();
        // 退出程序
        $this->quitProgram = new Channel(1);
        // 阻塞进程
        $this->waitGroup = new WaitGroup();

        $this->writeFile = new Channel();

        // 命令行输出
        $this->terminal = new Terminal($input, $output, 'INFO');
        $this->terminal->message();
        $this->startUp = time();  // 启动时间

        static::$container = $container;
    }

    /**
     * 超时时间设置
     * @param int $timeout
     */
    public function setTimeout(int $timeout) : void {
        if (static::$stateCurrent === self::STATE_RUNNING) {
            return;
        }
        // 超时时间超过1小时，
        if ($timeout < -1 && $timeout > 3600) {
            $timeout = 120;
        }
        $this->timeout = $timeout;
    }

    /**
     * 最大并发请求数量
     * @param int $requests
     */
    public function setConcurrentRequestsNumber(int $requests = 8) : void
    {
        if (static::$stateCurrent === self::STATE_RUNNING) {
            return;
        }

        if ($requests < 1 || $requests > 10000) {
            $requests = 8;
        }
        $this->workerCount = $requests;
    }

    protected function getWorkerCount() : int
    {
        return $this->workerCount;
    }

    /**
     * 最大并发数量限制
     * @param int setQueueValue
     */
    public function setQueueLength(int $length = 0) : void
    {
        if (static::$stateCurrent === self::STATE_RUNNING) {
            return;
        }

        if ($length === 0 || $length < 1 ||
            $length <= $this->workerCount) {
            $length = $this->workerCount * 2;
        }

        $this->queueLength = $length;
    }

    protected function getQueueLength() : int
    {
        return $this->queueLength;
    }

    public function addFile(FileM3u8 $file) : void
    {
        $this->files[] = $file;
    }

    protected function installSignal()
    {
        Process::signal(SIGINT, function(){
            if ($this->flag === false) {
                $this->flag = true;
                // 触发定时器轮询安全退出进程
                $this->quitProgram->push(true);
                var_dump('SIGINT');
            }
        });
    }

    /**
     * @param FileM3u8 $file
     * @return void
     * @throws \Exception
     */
    protected function downloadFileM3u8(FileM3u8 $file) : void
    {
        // 分片文件集合
        $files  = $file->transportStreamArray();
        // 视频播放时长
        $file->setTransportStreamFile($files);
        $second = $file->getPlaySecond();
        $file->setPlayTime($second);
        $file->setState(FileM3u8::STATE_CONTENT_SUCCESS);
        // 初始化进度条
        $file->cliProgressBar->setDetails("下载中[ {$file->getFilename()} ]: ");
        $file->cliProgressBar->setBarLength(100);
        $file->cliProgressBar->setSteps(\count($file));
    }

    protected function hasFiles() :bool {
        return !empty($this->files());
    }

    public function files() : array
    {
        return $this->files;
    }

    /**
     * @return int
     */
    protected function fileFinCount() : int {
        $counter = 0;
        if (!$this->hasFiles())
        {
            return $counter;
        }
        /**
         * @var FileM3u8 $file
         */
        foreach ($this->files() as $file) {
            if (!$file instanceof FileM3u8) {
                continue;
            }
            if($file->getState() === FileM3u8::STATE_CONTENT_SUCCESS) {
                $counter++;
            }
        }
        return $counter;
    }

    protected function fileFailCount() :int {
        $counter = 0;
        if (!$this->hasFiles())
        {
            return $counter;
        }
        /**
         * @var FileM3u8 $file
         */
        foreach ($this->files() as $file) {
            if (!$file instanceof FileM3u8) {
                continue;
            }
            if($file->getState() === FileM3u8::STATE_FAIL) {
                $counter++;
            }
        }
        return $counter;
    }

    /**
     * @throws \Exception
     */
    protected function downloadStart() : void
    {
        if (!$this->hasFiles()) {
            throw new \Exception('未发现下载任务');
        }
        // 运行中
        static::$stateCurrent = self::STATE_RUNNING;
        foreach ( $this->files() as $id => $file) {
            if (!$file instanceof FileM3u8) {
                continue;
            }
            $this->waitGroup->add();
            Coroutine::create(function() use($file,$id) {
                Coroutine::defer(function() {
                    $this->waitGroup->done();
                });
                try {
                    // 下载一个文件
                    $this->downloadFileM3u8($file);
                    $this->terminal->print(sprintf("[%d] 搜索网络文件完成: 《%s》", $id, $file->getFilename()));
                } catch (\Exception | \Error $e) {
                    // 标记失败任务
                    $file->setState(FileM3u8::STATE_FAIL);
                    // 记录错误文件日志
                    static::$container['logger']
                        ->error(
                            __METHOD__.":异常日志:{$file->getUrl()} -> {$e->getMessage()},错误代码: {$e->getCode()}."
                        );
                    $file->cliProgressBar->setColorToRed();
                    // 获取资源失败 101: 请求失败  102: 文件内容无效
                    if ($e->getCode() === 101 || $e->getCode() === 102) {
                        $file->setMessage($e->getMessage());
                    }
                }
            });
        }
        // 超时?
        if (!$this->waitGroup->wait($this->timeout)) {
            throw new \Exception('下载任务存在部分超时!');
        }
    }

    public function start()
    {
        try
        {
            if (self::STATE_RUNNING === self::$stateCurrent) {
                throw new \RuntimeException('已经存在运行实例');
            }
            // 查找网络文件
            $this->downloadStart();
        } catch (\Exception $e) {
            $this->terminal->print($e->getMessage());
            return;
        } finally {
            $this->terminal->print(
                sprintf("搜索网络文件结束，成功:[%d]个,失败:[%d]个",
                    $this->fileFinCount(),
                    $this->fileFailCount()
                )
            );
        }

        // 安装信号处理器
        $this->installSignal();
        // 投递文件到协程通道
        $this->dropOffDocuments();
        // 管控进程
        $this->monitorWorkerPool();
        // 协程挂起
        $this->waitGroup->wait();
        // 创建本地视频文件
        $this->createFileVideo();
        // 统计下载结
        $this->downloadResults();
        // 输出下载时间
        $this->timeCost();
    }

    protected function monitorWorkerPool() {
        Coroutine::create(function() {
           $this->quitProgram->pop();
           $id = Timer::tick(1000, function()use(&$id) {
               if ($this->queueChannel->length() > 0) {
                   return;
               }
               Timer::clear($id);
               $this->queueChannel->close();
               $this->quitProgram->close();
               $this->hasQuit = true;
           });
        });
    }

    protected function dropOffDocuments()
    {
        // 队列任务
        $this->queueChannel = new Channel($this->getQueueLength());

        /**
         * @var FileM3u8 $m3u8File
         */
        foreach ($this->files() as $m3u8File)
        {
            // 失败不进行下载
            if ($m3u8File->getState() === FileM3u8::STATE_FAIL) {
                continue;
            }

            if ($m3u8File->exists()) {
                $m3u8File->cliProgressBar->addCurrentStep($m3u8File->count());
                $m3u8File->setState(FileM3u8::STATE_SUCCESS);
                continue;
            }
            $this->waitGroup->add();
            Coroutine::create(function()use($m3u8File) {
                Coroutine::defer( fn()=> $this->waitGroup->done() );
                /**
                 * @var TransportStreamFile $tsFile
                 */
                foreach ($m3u8File as $tsFile) {
                    // 停止运行
                    if($this->flag) {
                        return;
                    }

                    if ($tsFile->exists()) {
                        $tsFile->setState(TransportStreamFile::STATE_SUCCESS);
                        $m3u8File->cliProgressBar->addCurrentStep(1);
                        $m3u8File->setFileSize($tsFile->getFileSize());
                        continue;
                    }
                    $this->queueChannel->push($tsFile);
                }
            });

            // 下载文件队列任务
            $this->makeWorkerPool();
        }

        // 文件数据为空，自动触发安全退出
        $this->waitGroup->add();
        Coroutine::create(function() {
            Coroutine::defer(fn() => $this->waitGroup->done());
            while(!$this->queueChannel->isEmpty()) {
                Coroutine::sleep(1);
            }

            if (!$this->flag) {
                $this->quitProgram->push(true);
            }
        });
    }

    protected function makeWorkerPool()
    {
        for ($i = 1; $i <= $this->workerCount; $i++)
        {
            $this->waitGroup->add();
            Coroutine::create(function() {
                Coroutine::defer(function(){
                   $this->waitGroup->done();
                });

                while(true) {
                    /**
                     * @var $tsFile TransportStreamFile
                     */
                    $tsFile = $this->queueChannel->pop();
                    if (!$tsFile) {
                        return;
                    }
                    $this->downloadTsFragment($tsFile, 6, 15);
                }
            });
        }
    }

    protected function downloadTsFragment(TransportStreamFile $transportStreamFile, int $retry, int $timeout) : bool
    {
        $e = null;

        while($retry-- > 0) {
            try
            {
                if ($data = $transportStreamFile->getBody($timeout)) {
                    $transportStreamFile->setState(TransportStreamFile::STATE_SUCCESS);
                        // ts 视频文件大小
                        $fileSize = $transportStreamFile->saveFile($data);
                        $fileM3u8 = $transportStreamFile->getFileM3u8();
                        $fileM3u8->setFileSize($fileSize);
                        $fileM3u8->cliProgressBar->addCurrentStep(1);
                        // 100%进度条改变为绿色
                        if ($fileM3u8->cliProgressBar->getSteps() === $fileM3u8->cliProgressBar->getCurrentStep()) {
                            $fileM3u8->cliProgressBar->setColorToGreen();
                            $fileM3u8->cliProgressBar->setDetails("下载完成[ {$fileM3u8->getFilename()} ]: ");
                            $fileM3u8->cliProgressBar->display();
                            $fileM3u8->cliProgressBar->end();
                        } else {
                            // 不断显示任务进度条
                            $fileM3u8->cliProgressBar->display();
                            // 多任务下载可开启，命令行进度条显示才能正常
                            $fileM3u8->cliProgressBar->nl();
                        }
                        return true;
                    }
                $transportStreamFile->setState(TransportStreamFile::STATE_FAIL);
            } catch (\Exception | \Error $e) {
                // 下载失败,记录日志
                $transportStreamFile->setState(TransportStreamFile::STATE_FAIL);
                if ($retry === 0)
                {
                    static::$container['logger']
                        ->error(__METHOD__."-> {$transportStreamFile->getUrl()} 程序异常: {$e}");
                }
            } finally {
                // 网络请求失败,记录下载失败
                $transportStreamFile->getFileM3u8()
                    ->setState(
                is_null($e) ? FileM3u8::STATE_SUCCESS : FileM3u8::STATE_FAIL
                    );
            }
        }
        return false;
    }

    protected function createFileVideo() {
        $waitGroup = null;

        if($this->flag) {
            return;
        }

        /**
         * @var $file FileM3u8
         */
        foreach ($this->files() as $id => $file) {
            // 失败不进行合并
            if ($file->getState() === FileM3u8::STATE_FAIL) {
                continue;
            }
            // 文件存在不创建协程生成视频文件
            if ($file->exists()) {
                $file->setState(FileM3u8::STATE_SUCCESS);
                continue;
            }
            if ($file->cliProgressBar->getSteps() !== $file->cliProgressBar->getCurrentStep()) {
                $file->setState(FileM3u8::STATE_FAIL);
                 continue;
            }
            //  文件下载完成,开始合并
            if (is_null($waitGroup)) {
                $waitGroup = new WaitGroup();
            }

            $waitGroup->add();
            Coroutine::create(function() use($file,$waitGroup) {
                Coroutine::defer(fn()=>$waitGroup->done());
                try
                {
                    /**
                     * @var $dispatcher EventDispatcher
                     */
                    $dispatcher = self::$container['dispatcher'];
                    // 事件为空，添加默认处理器[二进制]
                    if (!$dispatcher->hasListeners(CreateVideoFileEvent::NAME)) {
                        $dispatcher->addListener(CreateVideoFileEvent::NAME,
                            [
                                new CreateBinaryVideoListener(), CreateBinaryVideoListener::METHOD_NAME
                            ]
                        );
                    }
                    // 视频转换合并
                    $dispatcher->dispatch(new CreateVideoFileEvent($file), CreateVideoFileEvent::NAME);
                } catch (\Exception | \Error $e) {
                    // 写入日志文件
                    static::$container['logger']->error(__METHOD__.":异常日志:{$e->getMessage()}, 错误代码: {$e->getCode()}.");
                }
            });
        }
        if (!is_null($waitGroup)) {
            $waitGroup->wait();
        }
    }

    protected function downloadResults() {
        $output = $this->terminal->getOutput();
        if (is_null($output)) {
            return;
        }
        // 下载结果
        $result = [];
        /**
         * @var $file FileM3u8
         */
        foreach ($this->files() as $id => $file) {
            $info['id']           = ++$id;
            $info['filename']     = $file->getFilename();
            $info['count']        = \count($file);
            $info['local']        = $file->cliProgressBar->getCurrentStep();
            $info['now']          = $file->getPlaySecondFormat();
//            $info['save_path']    = \realpath($file->getRealFilename());
            $info['filesize']     = $file->getFileSizeFormat();
            $info['status']       = $file->getStateText();
            $result[] = $info;
        }
        $fileTable = new Table($output);
        $fileTable->setHeaders(['ID', '视频名','任务数量', '本地数量', '播放时长', '文件大小', '下载状态']);
        $fileTable->setRows($result);
        $fileTable->render();
    }

    protected function timeCost() {
        // 分钟数
        $timeCost = time() - $this->startUp;
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
        $fileNumber = \count($this->files());
        print date('Y-m-d H:i:s'). " >下载用时{$timeCost}{$unit}, 完成({$fileNumber})个任务.\n";
    }
}
