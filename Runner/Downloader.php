<?php declare(strict_types=1);

namespace Downloader\Runner;

use Dariuszp\CliProgressBar;
use Pimple\Container;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Swoole\Coroutine\WaitGroup;
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

    // 退出状态
    protected const STATE_QUIT = 3;

    // 正在运行
    protected const STATE_RUNNING = 2;

    // 正在启动
    protected const STATE_STARTING = 1;

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
     | 并发数量     $workerCount
     | 队列大小     $queueSize
     | 任务队列     $queueMaxSizeChannel
     | 挂起全部协程  $waitGroup
     | 退出全部协程  $quitProgram
     -----------------------------------------*/
    public int $queueSize      = 90;
    protected int $workerCount = 30;
    protected Channel $queueChannel;
    protected WaitGroup $waitGroup;
    protected Channel $quitProgram;
    protected Channel $writeFile;
    protected bool $quitProgramValue = false;

    /**
     * Downloader constructor.
     * @param Container $container
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    public function __construct(Container $container, InputInterface $input, OutputInterface $output)
    {
        // 协程数量
        $this->workerCount = $this->getConcurrencyValue();
        // 队列任务
        $this->queueChannel = new Channel($this->getQueueValue());
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
        // 超时时间超过1小时，
        if ($timeout < -1 && $timeout > 3600) {
            $timeout = 120;
        }
        $this->timeout = $timeout;
    }

    /**
     * 设置最大并发数量
     * @param int $value
     */
    public function setConcurrencyValue(int $value = 0) : void
    {
        if ($value < 5 || $value > 3000) {
            $value = 30;
        }
        $this->workerCount = $value;
    }

    protected function getConcurrencyValue() : int
    {
        return $this->workerCount;
    }

    /**
     * 设置队列大小
     * @param int setQueueValue
     */
    public function setQueueValue(int $value = 0) : void
    {
        if ($value < 80 || $value > 3000) {
            $value = $this->workerCount * 4;
        }
        $this->queueSize = $value;
    }

    protected function getQueueValue() : int
    {
        return $this->queueSize;
    }

    /**
     * 添加多个下载任务
     * @param array $files
     */
    public function addFiles(array $files) : void
    {
        $result = [];
        foreach ($files as $file)
        {
            if ($file instanceof FileM3u8)
            {
                $result[] = $file;
            }
        }
        $this->files = $result;
    }

    public function addFile(FileM3u8 $file) : void
    {
        $this->files[] = $file;
    }

    protected function isEmpty($files = null) : bool
    {
        if (is_null($files)) {
            $files =  $this->files;
        }
        return empty($files);
    }

    protected function installSignal()
    {
        foreach ([SIGINT] as $sign)
        {
            Coroutine::create(function()use($sign) {
                Coroutine\System::waitSignal($sign);
                switch ($sign)
                {
                    case SIGTERM:
                        var_dump('SIGTERM');
                        break;
                    case SIGINT:
                        var_dump('SIGINT');
                        $this->quitProgram->push(true);
                        break;
                }
            });
        }
    }

    public function files() : array
    {
        return $this->files;
    }

    /**
     * @return bool|null
     */
    protected function searchDone() : ?bool
    {
        // 未发现可下载的任务
        if ($this->isEmpty()) {
            return null;
        }

        foreach ( $this->files() as $file) {
            if (!$file instanceof FileM3u8) {
                continue;
            }
            $this->waitGroup->add();
            Coroutine::create(function() use($file) {
                try {
                    // 分片文件集合
                    $files  = $file->transportStreamArray();
                    // 视频播放时长
                    $file->setTransportStreamFile($files);
                    $second = $file->getPlaySecond();
                    $file->setPlayTime($second);
                    $file->setState(FileM3u8::STATE_CONTENT_SUCCESS);
                } catch (\Exception | \Error $e) {
                    static::$container['logger']->error(__METHOD__.":异常日志:{$e->getMessage()}, 错误代码: {$e->getCode()}.");
                    // 标记失败任务
                    $file->setState(FileM3u8::STATE_FAIL);
                    // 获取资源失败 101: 请求失败  102: 文件内容无效
                    if ($e->getCode() === 101 || $e->getCode() === 102) {
                        $file->setMessage($e->getMessage());
                    }
                } finally {
                    $this->waitGroup->done();
                }
            });
        }
        // 任务下载超时
        return $this->waitGroup->wait($this->timeout);
    }

    protected function searchNetworkFile()
    {
        // 没有发现任务
        if (is_null( $done = $this->searchDone() )) {
            $this->terminal->print("未发现下载任务");
            return;
        } elseif (!$done) {
            // 下载任务全部超时
            $this->terminal->print("下载任务全部超时失败");
            return;
        }

        // 成功数量
        $all = $success = 0;
        /**
         * @var FileM3u8 $file
         */
        foreach ($this->files() as $num => $file) {
            $this->terminal->print(sprintf("[%d] 搜索网络文件完成: 《%s》", $num, $file->getFilename()));
            $all++;
            if ($file->getState() === FileM3u8::STATE_CONTENT_SUCCESS) {
                $success++;
            }
        }
        $this->terminal->print(sprintf("搜索网络文件结束，成功:[%d]个,失败:[%d]个", $success, $all - $success));
    }

    public function start()
    {
        if (self::STATE_RUNNING === self::$stateCurrent) {
           throw new \RuntimeException('无效启动状态!');
        }
        // 安装信号处理器
        $this->installSignal();
        // 查找网络文件
        $this->searchNetworkFile();
        // 写入文件队列，进行下载文件任务
        $this->makeProducers();
        // 绘制进度条
        $this->drawCliProgressBar();
        // 管控进程
        $this->monitorWorkerPool();
        // 协程挂起
        $this->waitGroup->wait();
        // 开启协程加速生成视频
        $this->createNewFiles();
        // 统计下载结果
        $this->downloadResults();
        // 输出下载时间
        $this->timeCost();
    }

    protected function monitorWorkerPool() {
        Coroutine::create(function() {
           $this->quitProgramValue = $this->quitProgram->pop();
           $id = Timer::tick(1000, function()use(&$id) {
               if ($this->queueChannel->length() > 0) {
                   return;
               }
               Timer::clear($id);
               $this->queueChannel->close();
           });
        });
    }

    protected function makeProducers()
    {
        $m3u8Files = $this->files();
        /**
         * @var FileM3u8 $m3u8File
         */
        foreach ($m3u8Files as $m3u8File)
        {
            if ($m3u8File->exists()) {
                $m3u8File->setState(FileM3u8::STATE_SUCCESS);
                continue;
            }
            Coroutine::create(function()use($m3u8File) {
                /**
                 * @var TransportStreamFile $tsFile
                 */
                foreach ($m3u8File as $tsFile) {
                    if ($tsFile->exists()) {
                        $m3u8File->setCurrentStep(1);
                        $m3u8File->setFileSize($tsFile->getFileSize());
                        continue;
                    }
                    // 停止写入
                    if($this->quitProgramValue) {
                        return;
                    }
                    $this->queueChannel->push($tsFile);
                }
            });

            // 下载文件队列任务
            $this->makeWorkerPool();
        }
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

                while(true)
                {
                    /**
                     * @var $file TransportStreamFile
                     */
                    $file = $this->queueChannel->pop();
                    if (!$file) {
                       break;
                    }
                    $this->downloadTsFragment($file, 3, 30);
                }
            });
        }
    }

    protected function downloadTsFragment(TransportStreamFile $transportStreamFile, int $retry, int $timeout) : bool
    {
        while($retry-- > 0) {
            try
            {
                if ($data = $transportStreamFile->getBody($timeout)) {
                    $transportStreamFile->setState(TransportStreamFile::STATE_SUCCESS);
                    // ts 视频文件大小
                        $fileSize = $transportStreamFile->saveFile($data);
                        $fileM3u8 = $transportStreamFile->getFileM3u8();
                        $fileM3u8->setFileSize($fileSize);
                        $fileM3u8->setCurrentStep(1);
                        return true;
                    }
                $transportStreamFile->setState(TransportStreamFile::STATE_FAIL);
            } catch (\Exception | \Error $e) {
                // 下载失败,记录日志
                $transportStreamFile->setState(TransportStreamFile::STATE_FAIL);
                static::$container['logger']->error(__METHOD__.":异常日志:{$e->getMessage()}, 错误代码: {$e->getCode()}.");
            }
        }
        return false;
    }

    protected function drawCliProgressBar() {
        /**
         * @var $file FileM3u8
         */
        foreach ($this->files() as $file) {
            $this->waitGroup->add();
            Coroutine::create(function()use($file) {
                Coroutine::defer(function() {
                   $this->waitGroup->done();
                });
                // 文件总数
                $length = $file->count();
                // 绘制命令行进度条
                $file->cliProgressBar->setDetails("下载中[ {$file->getFilename()} ]: ");
                $file->cliProgressBar->setBarLength(100);
                $file->cliProgressBar->setSteps($length);
                // 文件已存在，跳过执行
                if ($file->exists()) {
                    $file->cliProgressBar->setColorToGreen();
                    $file->cliProgressBar->setDetails("下载完成[ {$file->getFilename()} ]: ");
                    $file->cliProgressBar->setCurrentStep($length);
                    $file->cliProgressBar->display();
                    $file->cliProgressBar->end();
                    return;
                }
                $timerId = Timer::tick(1000, function()use(&$timerId, $file) {
                    if ($this->quitProgramValue) {
                        Timer::clear($timerId);
                        return;
                    }

                    // 协程不断读取任务，显示进度条
                    $file->drawCurrentProgress();

                    // 多任务下载可开启，命令行进度条显示才能正常
//                  $file->cliProgressBar->nl();;
                    if ($file->count() === $file->cliProgressBar->getCurrentStep()) {
                        // 标记视频下载完成
                        $file->setState(FileM3u8::STATE_SUCCESS);
                        $file->cliProgressBar->setColorToGreen();
                        $file->cliProgressBar->setDetails("下载完成[ {$file->getFilename()} ]: ");
                        $file->cliProgressBar->display();
                        $file->cliProgressBar->end();
                        $this->queueChannel->close();
                        Timer::clear($timerId);
                        return;
                    }
                });
                $file->cliProgressBar->end();
            });
        }
    }

    protected function createNewFiles() {
        $waitGroup = null;
        /**
         * @var $file FileM3u8
         */
        foreach ($this->files() as $file) {
            if ($file->getState() === FileM3u8::STATE_SUCCESS) {
                if (is_null($waitGroup)) {
                    $waitGroup = new WaitGroup();
                }
                $waitGroup->add();
                Coroutine::create(function() use($file,$waitGroup) {
                    Coroutine::defer(fn()=>$waitGroup->done());
                    // 文件存在
                    if ($file->exists()) {
                        return;
                    }
                    try
                    {
                        /**
                         * @var $dispatcher EventDispatcher
                         */
                        $dispatcher = self::$container['dispatcher'];
                        // 事件为空，添加默认处理器[二进制]
                        if (!$dispatcher->hasListeners(CreateVideoFileEvent::NAME)) {
                            $dispatcher->addListener(CreateVideoFileEvent::NAME, [new CreateBinaryVideoListener(), CreateBinaryVideoListener::METHOD_NAME]);
                        }
                        // 视频转换合并
                        $dispatcher->dispatch(new CreateVideoFileEvent($file), CreateVideoFileEvent::NAME);
                    } catch (\Exception | \Error $e) {
                        // 写入日志文件
                        static::$container['logger']->error(__METHOD__.":异常日志:{$e->getMessage()}, 错误代码: {$e->getCode()}.");
                    }
                });
            }
        }

        $waitGroup->wait();
        // 通知消费者退出
        if ($this->quitProgramValue === false) {
            $this->quitProgram->push(true);
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
            $info['now']          = $file->getPlaySecondFormat();
            $info['save_path']    = \realpath($file->getFilePath());
            $info['filesize']     = $file->getFileSizeFormat();
            $info['status']       = $file->getStateText();
            $info['download_url'] = $file->getUrl();
            $result[] = $info;
        }
        $fileTable = new Table($output);
        $fileTable->setHeaders(['ID', '视频名','分片数', '播放时长', '保存位置', '文件大小', '下载状态',  '网络地址']);
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
