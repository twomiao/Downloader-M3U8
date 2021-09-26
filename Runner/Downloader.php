<?php declare(strict_types=1);
namespace Downloader\Runner;

use Downloader\Runner\Contracts\DecodeVideoInterface;
use Downloader\Runner\Contracts\HttpRequestInterface;
use Pimple\Container;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Swoole\Coroutine\WaitGroup;
use Swoole\Process;
use Swoole\Timer;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class Downloader
{
    /**
     * 版本号
     */
    const VERSION = '2.0.1';

    /**
     * 命令行程序名称
     */
    const APP_NAME = 'Downloader-M3u8';

    /**
     * @var Container $container
     */
    protected static Container $container;

    /**
     * 启动状态
     * @var bool $runStatus
     */
    protected static bool $runStatus = false;

    /**
     * 待下载任务
     * @var array $tasks
     */
    protected static array $downloadList = [
//        ["parser"]=>
//            string(24) "Downloader\Parsers\M1905"
//          ["tasks"]=>
//          array(4) {
//            [0]=>
//            string(71) "https://vvvvv/20210917/SQ9MmzQ8/1000kb/hls/index.m3u8"
//            [1]=>
//            string(74) "https://vvvvv/zw0111-1231/GVG-106/1000kb/hls/index.m3u8"
//          }
    ];

    /**
     * 下载完成 M3U8文件
     * @var array $statistics
     */
    protected static array $m3u8Statistics = [
//        '613dcd948b260' => 10,
//        '613dcd948b261' => 10,
//        '613dcd948b262' => 10,
//        '613dcd948b263' => 10,
    ];

    /**
     * 统计下载结果
     * @var array $statistics
     */
    protected static array $statistics = [
//        [
//            '视频名称' => '',
//            '文件大小' => '',
//            '片段数量' => '',
//            '播放时长' => '',
//            '保存位置' => '',
//        ],
//        [
//            '视频名称' => '',
//            '视频大小' => '',
//            '片段数量' => '',
//            '播放时长' => '',
//            '保存位置' => '',
//        ]
    ];

    private int $start;

    /**
     *  终端输入
     * @var InputInterface $input
     */
    protected InputInterface $input;

    /**
     * 终端输出
     * @var OutputInterface $output
     */
    protected OutputInterface $output;

    /**
     * @var HttpRequest $httpClient
     */
    protected HttpRequest $httpClient;
    /**
     * @var LoggerInterface $logger
     */
    protected LoggerInterface $logger;

    /**
     * 命令行输出
     * @var Cmd $cmd
     */
    private Cmd $cmd;

    /**
     * 工作池大小
     * @var int $count
     */
    private int $poolCount = 0;

    /**
     **************************************
     * 协程池
     **************************************
     *  工作队列     $jobChannel
     *  挂起全部协程  $waitGroup
     *  退出全部协程  $quit
     **************************************
     */
    private Channel $jobChannel;
    private WaitGroup $waitGroup;
    private Channel $quit;
    private bool $workerQuit;
    private Channel $writeFile;

    /**
     * Downloader constructor.
     * @param Container $container
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param int $poolCount
     */
    public function __construct(Container $container, InputInterface $input, OutputInterface $output, int $poolCount = 35)
    {
        $this->jobChannel = new Channel($this->poolCount * 5);
        $this->waitGroup = new WaitGroup();
        $this->quit = new Channel();
        $this->writeFile = new Channel();
        $this->output = $output;
        $this->input = $input;
        $this->workerQuit = false;
        $this->cmd = new Cmd($input, $output, 'INFO');
        $this->poolCount = $poolCount < 1 ? 20 : $poolCount;
        $this->cmd->env();
        $this->start = time();  // 启动时间

        static::$container = $container;
        $this->httpClient = $container[HttpRequestInterface::class];
        $this->logger = $container[LoggerInterface::class];

        Process::signal(SIGINT, [$this, 'workerQuit']);
    }

    /**
     * 添加视频解析器
     * @param VideoParser $parser
     * @return $this
     */
    public function addParser(VideoParser $parser)
    {
        if (isset(static::$downloadList['parser'])) {
            throw new \InvalidArgumentException('Do not add video parsers repeatedly.');
        }

        static::$downloadList['parser'] = $parser;
        return $this;
    }

    /**
     * 添加下载任务
     * @param array $download_urls
     * @return $this
     */
    public function addTasks(array $download_urls)
    {
        if (!isset(static::$downloadList['parser'])) {
            throw new \InvalidArgumentException('No video parser found.');
        }
        static::$downloadList['tasks'] = (static::$downloadList['tasks'] ?? []) + $download_urls;
        return $this;
    }

    /**
     * 添加下载任务
     * @param string ...$download_urls
     * @return $this
     */
    public function addTask(string ... $download_urls)
    {
        if (!isset(static::$downloadList['parser'])) {
            throw new \InvalidArgumentException('No video parser found.');
        }
        static::$downloadList['tasks'] = $download_urls + (static::$downloadList['tasks'] ?? []);
        return $this;
    }

    public function start()
    {
        if (static::$runStatus === true) {
            return;
        }
        static::$runStatus = true;

        /**
         * @var $parserObject VideoParser 解析器对象
         * @var $tasks array 视频网络地址
         */
        $parserObject = static::$downloadList['parser'];
        $taskUrls     = static::$downloadList['tasks'];

        try {
            $files = $parserObject->load($taskUrls);
            if (empty($files)) {
                return;
            }
        } catch (\Exception $e) {
            $this->cmd->level('ERROR')->print($e->getMessage());
            return;
        }
        static::$downloadList['tasks'] = $files;
        $this->cmd->print(sprintf("发现M3U8文件[ %s ]个.", FileM3u8::$m3utFileCount));

        /**
         * ***********************
         * 运行下载任务
         *   Array
         *   (
         *      [Downloader\Parsers\M1905] => Array
         *      (
         *        [0] => https://video.com/m3u8/3278/m3u8.m3u8
         *        [1] => https://video.com/m3u8/3342/m3u8.m3u8
         *      )
         *   )
         * ***********************
         */
        Coroutine::create(function () use ($taskUrls) {
            Coroutine::defer(fn () => $this->quit->push(true));

            /**
             * @var FileM3u8 $m3u8File
             */
            foreach (static::$downloadList['tasks'] as $filename => $m3u8File) {
                $this->cmd->level('warn')->print(
                    sprintf("开始下载文件[ %s ] %s", $filename, $m3u8File->getM3u8Url())
                );

                // 初始化 0 用于统计当前文件是否下载成功
                static::$m3u8Statistics[$filename] = static::$m3u8Statistics[$filename] ?? 0;
                if ($m3u8File->exists()) {
                    $this->cmd->level('warn')->print("本地已存在['{$filename}']此文件!!");
                    continue;
                }

                // m3u8文件日志记录
                self::getContainer(LoggerInterface::class)->debug(
                    sprintf("====> 开始下载M3U8文件[ %s ] %s <====", $filename, $m3u8File->getM3u8Url())
                );

                /**
                 * @var FileM3u8 $m3u8File
                 * @var PartTs $fileTs
                 */
                foreach ($m3u8File->filesTs as $fileTs) {
                    if ($this->workerQuit) {
                        break;
                    }
                    $this->jobChannel->push([$fileTs, $m3u8File]);
                }
            }
        });
        $this->workerPool();
        $this->writeFiles();
        $this->monitor();
        $this->statistics();
    }

    public function workerQuit()
    {
        if ($this->workerQuit === false) {
            $this->quit->push(true);
            $this->workerQuit = true;
            $this->logger->debug('===> 按下 ctrl+c 安全退出. <====');
        }
    }

    protected function writeFiles()
    {
        Coroutine::create(function () {
            Coroutine::create(fn () => $this->writeFile->pop());
            $count = 0;
            $timerId = Timer::tick(100, function () use (&$timerId, &$count) {
                /**
                 * @var FileM3u8 $m3u8File
                 * @var PartTs $fileTs
                 */
                foreach (static::$downloadList['tasks'] as $filename => $m3u8File) {
                    if (!$m3u8File->exists()) {
                        // 下载是否已经完成，开始写入文件
                        static::$m3u8Statistics[$filename] ??= 0;
                        // 5770 // 5738
                        if ($m3u8File->tsCount() == static::$m3u8Statistics[$filename]) {
                            $m3u8File->putFile();
                        }
                    } else {
                        $count++;
                    }

                    if ($count == FileM3u8::$m3utFileCount) {
                        Timer::clear($timerId);
                        return;
                    }
                }
                if ($this->workerQuit) {
                    Timer::clear($timerId);
                    return;
                }
            });
        });
    }

    protected function statistics()
    {
        /**
         * @param FileM3u8 $m3u8File
         */
        foreach (static::$downloadList['tasks'] as $filename => $m3u8File) {
            $fileSize = 0;
            try {
                // '视频名称', '网络地址', '片段数量', '播放时长', '保存位置', '文件大小'
                $row['file_name'] = $filename;
                $row['m3u8_url'] = $m3u8File->getM3u8Url();
                $row['ts_count'] = $m3u8File->tsCount();
                $row['play_at'] = $m3u8File->getPlayTime();
                $row['put_dir'] = $m3u8File->getPutFileDir();
                if (!$m3u8File->exists()) {
                    // 判断文件完整性
                    $number = $m3u8File->tsCount() - static::$m3u8Statistics[$filename];
                    if ($number > 0) {
                        $download_status = sprintf("失败任务 (%s)", $number);
                    } else {
                        // === 0
                        $m3u8File->putFile();
                        $fileSize = $m3u8File->getFileSize();
                        $download_status = '成功';
                    }
                } else {
                    $download_status = '成功';
                    $fileSize = $m3u8File->getFileSize();
                }

                $row['file_size'] = $fileSize;
                $row['download_status'] = $download_status;

            } catch (FileException $e) {
                $row['download_status'] = '失败';
                $this->cmd->level('warn')->print($e->getMessage());
            }
            static::$statistics[] = $row;
        }

        //   '视频名称' => '',
        //   '文件大小' => '',
        //   '片段数量' => '',
        //   '播放时长' => '',
        //   '保存位置' => '',
        $tableStatistics = new Table($this->output);
        $tableStatistics->setHeaders(['视频名称', '网络地址', '片段数量', '播放时长', '保存位置', '文件大小', '下载状态']);
        $tableStatistics->setRows(static::$statistics);
        $tableStatistics->render();

        $time = (time() - $this->start) / 60;
        $this->cmd->level('info')->print("全部下载完成用时: " . sprintf("%0.2f %s!", $time, '分钟'));
    }

    protected function workerPool(): void
    {
        for ($i = 1; $i <= $this->poolCount; $i++) {
            $this->waitGroup->add();
            Coroutine::create(function () {
                Coroutine::defer(fn () => $this->waitGroup->done());
                while (1) {
                    /**
                     * @param PartTs|bool
                     * @param FileM3u8 $fileM3u8
                     */
                    $data = $this->jobChannel->pop();

                    if ($data === true) {
                        return;
                    }

                    if (is_array($data)) {
                        [$fileTs, $fileM3u8] = $data;
                        $this->cmd->level('debug')->print("开始下载 {$fileTs}.");
                        $this->downloadTsFragment($fileTs, $fileM3u8);
                    }
                }
            });
        }
    }

    protected function monitor(): void
    {
        Coroutine::create(function () {
            $this->quit->pop();
            $timerId = Timer::tick(100, function () use (&$timerId) {
                if ($this->jobChannel->length() > 0) {
                    return;
                }
                Timer::clear($timerId);

                while ($this->poolCount--) {
                    if ($this->jobChannel->push(true) === false) {
                        break;
                    }
                }

                $this->writeFile->push(true);
                $this->jobChannel->close();
                $this->writeFile->close();
            });
        });

        $this->waitGroup->wait();
    }

    /**
     * 下载 TS 帧片段
     * @param PartTs $fileTs
     * @param FileM3u8 $fileM3u8
     */
    public function downloadTsFragment(PartTs $fileTs, FileM3u8 $fileM3u8)
    {
        $filename = $fileM3u8->getFilename();
        // m3u8 文件名称
        if ($fileTs->exists()) {
            static::$m3u8Statistics[$filename]++;
            return;
        }

        do {
            try {
                $resp = $this->httpClient->send($fileTs->getTsUrl());
            } catch (HttpResponseException $e) {
                $this->logger->error("发生错误: {$fileTs}");
                $this->cmd->level('error')->print("发生错误 {$fileTs}.");
                return;
            }
            $fileSize = (int)$resp->getHeaders('content-length');
            $statusCode = (int)$resp->getHeaders('status_code');
            $content = $resp->getData();
        } while ($fileSize != strlen($content) && $statusCode === 200);

        try {
            if ($statusCode === 200) {
                /**
                 * @var VideoParser $parserObject
                 */
                $parserObject = static::$downloadList['parser'];
                if ($fileM3u8->isEncrypt() && $parserObject instanceof DecodeVideoInterface) {
                    $content = $parserObject::decode($fileM3u8, $content, $fileTs->getTsUrl());
                }

                $fileTs->putFile($content);
                // 下载完成
                static::$m3u8Statistics[$filename]++;
                return;
            }
            throw new \Exception(
                sprintf("下载失败分片网络地址: %s, 状态码: %d", (string)$fileTs, $statusCode)
            );
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            $this->cmd->level('error')->print($e->getMessage());
        }
    }

    /**
     * @param null $key
     * @return mixed|Container
     * @throws \Exception
     */
    public static function getContainer($key = null)
    {
        if (empty(static::$container)) {
            throw new \Exception('Container is null.');
        }

        if (empty($key)) {
            return static::$container;
        }

        return static::$container[$key];
    }

    public static function appName(string $message)
    {
        return self::APP_NAME . ' ' . $message;
    }

    public static function version()
    {
        return self::VERSION;
    }
}
