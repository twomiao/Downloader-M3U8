<?php declare(strict_types=1);

namespace Downloader\Runner;

use Co\Channel;
use Downloader\Runner\Decrypt\DecryptionInterface;
use ProgressBar\Manager;
use ProgressBar\Registry;
use Psr\Container\ContainerInterface;
use Swoole\Coroutine;

class Downloader
{
    /**
     * 版本号
     */
    const VERSION = '1.0.1';

    // 下载超时
    const AUTO_EXIT_DOWNLOAD_TIMEOUT = 900;

    /**
     * 解析器
     * @var $movieParsers array
     */
    protected $movieParsers;

    /**
     * 解析器对象
     * @var $movieParsers array
     */
    protected $movieParserInstances;

    /**
     * @var ContainerInterface $container
     */
    protected $container;

    /**
     * 下载集合
     * @var $queue
     */
    protected $groups = [];

    /**
     * 失败队列
     */
    protected $failQueue;

    /**
     * 视频文件总数
     * @var int $groupM3u8Sum
     */
    protected $groupM3u8Sum = 0;

    /**
     * 下载成功文件
     * @var array $success
     */
    protected $success = [];

    /**
     * 解密接口实例列表
     * @var array $decrptInterface
     */
    protected $decrptInterface = [];

    /**
     * @var array $config
     */
    protected $config = [
        'output'      => '',
        'concurrent'  => 25,
    ];

    /**
     * 下载次数
     * @var array $successStatistics
     */
    protected $successStatistics = [];

    /**
     * Downloader constructor.
     * @param ContainerInterface $container
     * @param array $config
     */
    public function __construct(ContainerInterface $container, array $config = [])
    {
        $this->config    = array_merge($this->config, $config);
        $this->container = $container;

        echo Utils::baseInfo();
    }

    /**
     * @param MovieParser $movieParser 解析器实例
     * @param array $params
     * @param DecryptionInterface|null $decrypt 解密实例
     * @return $this
     */
    public function setMovieParser(MovieParser $movieParser, array $params, DecryptionInterface $decrypt = null)
    {
        foreach ($params as $param) {
            if ($this->hasLegality($param)) {
                throw new AddressLegalityException($param);
            }
        }
        $this->groupM3u8Sum += count($params);

        $movieParser->setContainer($this->container);
        $movieParserClass = get_class($movieParser);

        if (isset($this->movieParserInstances[$movieParserClass])) {
            throw new MovieParserInstancesException("Parser may be overwritten, MovieParser is: {$movieParserClass}.");
        }
        // 对象实例
        $this->movieParserInstances[$movieParserClass] = $movieParser;
        $this->decrptInterface[$movieParserClass] = $decrypt;

        // 类字符串
        $this->movieParsers[$movieParserClass] = $params;
        return $this;
    }

    protected function startParsingFile(): void
    {
        $color = $this->container->get('color');

        echo $color("Loading multiple M3U8 files：")->white()->bold()->highlight('yellow') . PHP_EOL . PHP_EOL;

        foreach ($this->movieParsers as $movieParserClass => $m3u8List) {
            $movieParserInstance = $this->getMovieParserInstance($movieParserClass);
            if (empty($movieParserInstance)) {
                continue;
            }

            /**
             * @var $m3u8Files array
             */
            $m3u8Files = $movieParserInstance
                ->setM3u8s($m3u8List)
                ->setDecryptionInterface($this->decrptInterface)
                ->setConfig($this->config)
                ->setMovieParserClass($movieParserClass)
                ->runParser();

            /**
             * @var $m3u8File M3u8File
             */
            foreach ($m3u8Files as $index => $m3u8File) {
                if ($m3u8File instanceof M3u8File) {
                    // 请求成功
                    $this->groups[$movieParserClass][] = $m3u8File;
                    continue;
                }
                $this->failQueue[$movieParserClass][] = $m3u8File;
            }

        }
        echo PHP_EOL;
        echo $color("Successfully analyzed ( {$this->groupM3u8Sum} ) files and started downloading ......")
                ->white()
                ->bold()
                ->highlight('green') . PHP_EOL . PHP_EOL;
    }

    /**
     * 获取解析器实例
     * @param string $movieParser
     * @return MovieParser|null
     */
    protected function getMovieParserInstance(string $movieParser)
    {
        return $this->movieParserInstances[$movieParser] ?? null;
    }

    protected function hasLegality($m3u8)
    {
        return !preg_match('/http[s]*:\/\/([\w.]+\/?)\S*/Uis', $m3u8);
    }

    public function run()
    {
        $this->startParsingFile();

        foreach ($this->groups as $movieParserClass => $m3u8Files) {
            /**
             * @var $m3u8File M3u8File
             */
            foreach ($m3u8Files as $index => $m3u8File) {

                $filename = $m3u8File->getM3u8Id();
                $basename = $m3u8File->getBasename();
                $output = $m3u8File->getOutput();

                clearstatcache();
                if (is_file($filename)) {
                    $this->success['success'][] = $basename;
                    continue;
                }

                echo "Downloading file {$basename}: " . PHP_EOL;
                if (!Utils::mkdirDiectory($output)) {
                    throw new \RuntimeException("mkdir fail, dir is:{$output}.");
                }

                try {
                    $this->startMergeTask
                    (
                          $m3u8File
                        , $progressBar = $this->startDownloadingSingleTask($m3u8File)
                    );
                } catch (\Throwable $e) {
                    $this->container->get('log')->record($e);
                }
            }
        }

        $this->resultStatistics();
    }

    /**
     * 开始下载任务M3u8文件
     * @param M3u8File $m3u8File
     * @return Manager
     */
    protected function startDownloadingSingleTask(M3u8File $m3u8File): Manager
    {
        $tsCount  = $m3u8File->getTsCount();
        $wg       = $m3u8File->getChannel();
        $splQueue = $m3u8File->getSplQueue();
        /**
         * @var $progressBar Manager
         */
        $progressBar = $this->container->get('bar');
        $registery = $progressBar->getRegistry();
        $registery->setValue('max', $tsCount);
        $progressBar->setRegistry($registery);

        while (!$splQueue->isEmpty()) {
            $remoteTs = $splQueue->shift();
            $this->singleTaskDownload($m3u8File, $progressBar, $wg, $remoteTs);
        }

        $m3u8File->closedChannel();

        return $progressBar;
    }


    protected function singleTaskDownload(M3u8File $m3u8File, Manager $progressBar, Channel $wg, string $remoteTs)
    {
        $wg->push(true);

        Coroutine::create(function () use ($progressBar, $wg, $m3u8File, $remoteTs) {
            $output = $m3u8File->getOutput();

            defer(function () use ($wg) {
                $wg->pop();
            });

            $targetTs = "{$output}" . basename($remoteTs);
            $basename = basename($targetTs);
            clearstatcache();
            if (is_file($targetTs)) {
                $this->successStatistics[$targetTs] = $basename;
                $progressBar->advance();
                return;
            }

            /**
             * @var HttpClient $client
             */
            $client = $this->container->get('client');

            try {
                $client->get()->request($remoteTs);
            } catch (RetryRequestException $e) {
                $this->container->get('log')->record($e);
            } finally {
                $client->closed();
            }

            if ($client->getBodySize() > 2 * 1024) {
                $data = $client->getBody();
                if ($m3u8File->getDecryptKey()) {
                    $data = $m3u8File->getDecryptInstance()->decrypt(
                        $data,
                        $m3u8File->getDecryptKey(),
                        $m3u8File->getDecryptIV()
                    );
                }

                if ($data) {
                    $this->successStatistics[$targetTs] = $basename;
                    Utils::writeFile($targetTs, $data);
                    $progressBar->advance();
                }
            }
        });
    }

    /**
     * 开始合并下载任务
     * @param M3u8File $m3u8File
     * @param Manager $progressBar
     */
    protected function startMergeTask(M3u8File $m3u8File, Manager $progressBar)
    {
        $color    = $this->container->get('color');
        $output   = $m3u8File->getOutput();
        $tsCount  = $m3u8File->getTsCount();
        $video    = $m3u8File->getM3u8Id();
        $splArray = $m3u8File->getMergedTsArray();
        $wg       = $m3u8File->getChannel();
        $bindMap  = $m3u8File->getBindTsMap();
        $tsTotalCount = $splArray->getSize();

        $flag = true;

        // 失败任务重试
        if ($this->successStatistics)
        {
            $differentElements = array_diff($splArray->toArray(), $this->successStatistics);

            while ($basename = array_pop($differentElements)) {
                if ($flag) {
                    /**
                     * @var $registery Registry
                     */
                    $registery = $progressBar->getRegistry();

                    $registery->setValue('current', $tsTotalCount - count($differentElements));
                    $progressBar->setRegistry($registery);

                    $flag = false;
                }


                if (isset($bindMap[$basename]))
                {
                    // 失败任务重试
                    $remoteTs = $bindMap[$basename];
                    //
                    $this->container->get('log')->record("retry task, remote_ts=" .$remoteTs);
                    $this->singleTaskDownload($m3u8File, $progressBar, $wg, $remoteTs);
                }
            }

            if ($flag === false) {
                $m3u8File->closedChannel();
            }
        }


        // 超时处理开关, 30分钟未完成任务下载自动你退出任务。
        $timeout = time();
        $basename = $m3u8File->getBasename();

        // 成功队列数量
        $successStatistics = 0;

        while ($successStatistics <= $splArray->count()) {

            // 成功队列数量
            $successStatistics = count($this->successStatistics);

            // 匹配M3U8文件长度,考虑开始保存任务生成视频文件。
            if ($successStatistics === $splArray->count()) {

                $relpath = realpath($output);
                echo "Saving files：{$relpath}.mp4" . PHP_EOL;

                /**
                 * @var $progressBar Manager
                 */
                $progressBar = $this->container->get('bar');
                /**
                 * @var $registery Registry
                 */
                $registery = $progressBar->getRegistry();
                $registery->setValue('max', $tsCount);
                $progressBar->setRegistry($registery);

                foreach ($splArray as $index => $file) {
                    $filename = "{$output}/{$file}";
                    clearstatcache();
                    if (is_file($filename)) {
                        $data = file_get_contents($filename);
                        Utils::writeFile($video, $data, true);
                        unlink($filename);
                        $progressBar->advance();
                    }
                }
                $this->success['success'][] = $basename;
                $this->successStatistics = [];
                echo PHP_EOL;
                echo $color("Download ( {$basename} ) file complete!")->white()->bold()->highlight('green') .
                    str_repeat(PHP_EOL, 2);
                break;
            } elseif ((time() - $timeout) > static::AUTO_EXIT_DOWNLOAD_TIMEOUT) {
                echo PHP_EOL;
                echo $color("File ( {$basename} ) download timed out.")->white()->bold()->highlight('red') .
                    str_repeat(PHP_EOL, 2);
                break;
            } else {
                // 0.5 秒运行一次上面的代码
                Coroutine::sleep(0.5);
            }
        }

        echo PHP_EOL;
    }

    protected function resultStatistics()
    {
        $success = $this->sucessCount();
        $error = $this->groupM3u8Sum - $success;
        $color = $this->container->get('color');
        $successFiles = $this->successFiles();
        $failFiles = $this->failFiles();

        echo $color('完成任务统计: ')->white()->bold() . PHP_EOL . PHP_EOL;
        $success = $color($success)->white()->bold()->highlight('green');
        echo $color("成功数量: {$success} 个, 失败数量: ")->white()->bold();
        $error = $color($error)->white()->bold()->highlight('red');
        echo $color("{$error} 个.")->white()->bold() . PHP_EOL . PHP_EOL;

        echo $color('已成功文件记录: ( ')->white()->bold();
        echo $color("{$successFiles}")->white()->bold()->highlight('green');
        echo $color(' ), ')->white()->bold();
        echo $color('失败文件记录: ( ')->white()->bold();
        echo $color("{$failFiles}")->white()->bold()->highlight('red');
        echo $color(' ).')->white()->bold() . PHP_EOL . PHP_EOL;

    }

    protected function successFiles()
    {
        if (isset($this->success['success'])) {
            return implode(', ', array_values($this->success['success']));
        }

        return 'empty';
    }

    protected function failFiles()
    {
        if (isset($this->success['fail'])) {
            return implode(', ', array_values($this->success['fail']));
        }

        return 'empty';
    }

    protected function sucessCount(): int
    {
        if (isset($this->success['success'])) {
            return count($this->success['success']);
        }
        return 0;
    }
}