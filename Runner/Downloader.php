<?php declare(strict_types=1);

namespace Downloader\Runner;

use Pimple\Container;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Swoole\Coroutine\WaitGroup;
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
     * 解析器
     * @var array $tasks
     */
    protected static array $parsers = [
        //   M1905::class => [
        //           'https://video.com/m3u8/3278/m3u8.m3u8',
        //           'https://video.com/m3u8/3342/m3u8.m3u8'
        //      ],
        //   YouKu::class => []
    ];

    /**
     * 任务记录
     * @var array $taskM3u8
     */
    protected static array $taskM3u8 = [];

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
     * ********************************************
     * 协程池
     * *************************************
     *  工作队列     $jobChannel
     *  挂起全部协程  $waitGroup
     *  退出全部协程  $quit
     * ****************************************
     */
    private Channel $jobChannel;
    private WaitGroup $waitGroup;
    private Channel $quit;
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
        // 启动时间
        $this->start = time();
        $this->jobChannel = new Channel($this->poolCount * 5);
        $this->waitGroup = new WaitGroup();
        $this->quit = new Channel();
        $this->writeFile = new Channel();
        static::$container = $container;
        $this->output = $output;
        $this->input = $input;
        $this->cmd = new Cmd($input, $output, 'INFO');
        $this->poolCount = $poolCount < 1 ? 20 : $poolCount;
        $this->cmd->env();
    }

    /**
     * 添加下载任务和解析器
     * @param Parser $parser
     * @param $url
     * @return $this
     */
    public function addParser(Parser $parser, $url)
    {
        $urls = [];
        if (is_string($url)) {
            $urls[] = $url;
        }

        // [hua::class =>  [url_01,url_02] ...;]
        static::$parsers[get_class($parser)] = $urls;
        return $this;
    }

    /**
     * @param array $tasks
     */
    public function addParsers(array $tasks): void
    {
        foreach ($tasks as $parserClass => $urls) {
            if (empty($urls)) {
                break;
            }

            if (!class_exists($parserClass)) {
                throw new \RuntimeException("{$parserClass} Class not found.");
            }

            try {
                $reflect = new \ReflectionClass($parserClass);
                if ($reflect->isSubclassOf(Parser::class)) {
                    static::$parsers[$parserClass] = $urls;
                }
                unset($reflect);
            } catch (\ReflectionException $e) {
            }
        }
    }

    public function start()
    {
        try {
            /**
             * @var $parserClass Parser
             * @var $m3u8FileUrls array
             */
            foreach (static::$parsers as $parserClass => $m3u8FileUrls) {
                /**
                 * ***************************************
                 * 来自网络资源数据，加载到解析器内存
                 *  解析器内存数据，加载到Downloader 运行下载
                 * ***************************************
                 */
                $m3u8Files = $parserClass::start($m3u8FileUrls);
                static::$taskM3u8[$parserClass] = $m3u8Files[$parserClass];
                unset(static::$parsers[$parserClass]);
            }
        } catch (\Exception $e) {
            $this->cmd->level('ERROR')->print($e->getMessage());
            return;
        }

        if (empty(static::$taskM3u8)) {
            return;
        }

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
        Coroutine::create(function () {

            Coroutine::defer(fn () => $this->quit->push(true));

            foreach (static::$taskM3u8 as $parserClass => $m3u8Files) {
                FileM3u8::$parserClass = $parserClass;
                /**
                 * @var FileM3u8 $m3u8File
                 */
                foreach ($m3u8Files as $filename => $m3u8File) {
                    $this->cmd->level('warn')->print(
                        sprintf("开始下载文件[ %s ] %s", $m3u8File->getFilename(), $m3u8File->getM3u8Url())
                    );
                    // 初始化 0 用于统计当前文件是否下载成功
                    static::$m3u8Statistics[$filename] = static::$m3u8Statistics[$filename] ?? 0;
                    if ($m3u8File->exists()) {
                        $this->cmd->level('warn')->print("本地已存在['{$filename}']此文件!!");
                        continue;
                    }

                    FileM3u8::$decryptKey = $m3u8File->getDecryptKey();
                    FileM3u8::$decryptMethod = $m3u8File->getDecryptMethod();

                    /**
                     * @var FileM3u8 $m3u8File
                     * @var PartTs $fileTs
                     */
                    foreach ($m3u8File->filesTs as $fileTs) {
                        $fileTs->setMu38Name((string)$filename);
                        $this->jobChannel->push($fileTs);
                    }
                }
            }
        });
        $this->workerPool();
        $this->writeFiles();
        $this->monitor();
        $this->statistics();
    }

    protected function writeFiles()
    {
        Coroutine::create(function () {
            Coroutine::create(fn () => $this->writeFile->pop());
            $count = 0;
            $timerId = Timer::tick(100, static function () use (&$timerId, &$count) {
                foreach (static::$taskM3u8 as $parserClass => $m3u8Files) {
                    /**
                     * @var FileM3u8 $m3u8File
                     * @var PartTs $fileTs
                     */
                    foreach ($m3u8Files as $filename => $m3u8File) {
                        if (!$m3u8File->exists()) {
                            // 下载是否已经完成，开始写入文件
                            static::$m3u8Statistics[$filename] = static::$m3u8Statistics[$filename] ?? 0;
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
                }
                $count = 0;
            });
        });
    }

    protected function statistics()
    {
        foreach (static::$taskM3u8 as $parserClass => $m3u8Files) {
            /**
             * @var FileM3u8 $m3u8File
             * @var PartTs $fileTs
             */
            foreach ($m3u8Files as $filename => $m3u8File) {
                $fileSize = 0;
                try {
                    // '视频名称', '网络地址', '片段数量', '播放时长', '保存位置', '文件大小'
                    $row['file_name'] = $filename;
                    $row['m3u8_url'] = $m3u8File->getM3u8Url();
                    $row['ts_count'] = $m3u8File->tsCount();
                    $row['play_at'] = $m3u8File->getPlayTime('min') . '分钟';
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
                     * @var PartTs|bool $fileM3u8
                     */
                    $fileTs = $this->jobChannel->pop();

                    if ($fileTs === true) {
                        return;
                    }
                    $this->cmd->level('debug')->print("开始下载 {$fileTs}.");
                    $this->downloadTsFragment($fileTs);
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
     */
    public function downloadTsFragment(PartTs $fileTs)
    {
        // m3u8 文件名称
        $filename = $fileTs->m3u8Filename;
        if ($fileTs->exists()) {
            static::$m3u8Statistics[$filename]++;
            return;
        }

        do {
            $httpRequest = new HttpRequest((string)$fileTs, 'GET', ['CURLOPT_HEADER' => true, 'CURLOPT_NOBODY' => false]);
            try {
                $resp = $httpRequest->send();
            } catch (HttpResponseException $e) {
                static::$container[LoggerInterface::class]->error("发生错误: {$fileTs}");
                $this->cmd->level('error')->print("发生错误 {$fileTs}.");
                return;
            }
            $fileSize = (int)$resp->getHeaders('content-length');
            $content = $resp->getData();
        } while ($fileSize != strlen($content));

        /**
         * ***************************
         * 解密TS 内容
         * ***************************
         *
         * @var Parser $parserClass
         *
         * ***************************
         */
        $parserClass = FileM3u8::$parserClass;
        try {
            $content = $parserClass::decodeData($content);
        } catch (\Exception $e) {
            static::$container[LoggerInterface::class]->error("{$e->getMessage()}");
            $this->cmd->level('error')->print($e->getMessage());
            return;
        }
        $fileTs->putFile($content);
        // 下载完成
        static::$m3u8Statistics[$filename]++;
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
