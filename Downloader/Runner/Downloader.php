<?php

namespace Downloader\Runner;

use ProgressBar\Manager;
use ProgressBar\Registry;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine\Channel;
use Swoole\Coroutine;

class Downloader
{
    /**
     * 版本号
     */
    const VERSION = '1.0.1';

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
     * @var HttpClient $client
     */
    protected $client;

    /**
     * 目标队列
     * @var $queue
     */
    protected $targetQueue;

    /**
     * 失败队列
     */
    protected $failQueue;

    /**
     * 视频文件总数
     * @var int $m3u8Sum
     */
    protected $m3u8Sum = 0;

    /**
     * 已下载记录
     * @var array $historyComplete
     */
    protected $historyComplete = [];

    /**
     * @var array $config
     */
    protected $config = [
        'concurrent' => 40,
        'output' => ''
    ];

    /**
     * Downloader constructor.
     * @param ContainerInterface $container
     * @param array $config
     * @throws \Exception
     */
    public function __construct(ContainerInterface $container, array $config = [])
    {
        $this->config    = array_merge($this->config, $config);
        $this->container = $container;
        $this->client    = $this->container->get('client');
        $this->client->setContianer($container);

        if (!Utils::mkdirDiectory($this->config['output'])) {
            $dir = $this->config['output'];
            throw new \Exception("Error 视频导出目录创建失败:{$dir}.");
        }

        echo Utils::baseInfo();
    }

    public function setMovieParser(MovieParser $movieParser, array $params)
    {
        foreach ($params as $param) {
            if ($this->hasLegality($param)) {
                throw new AddressLegalityException($param);
            }
        }
        $this->m3u8Sum += count($params);

        $movieParser->setContainer($this->container);
        $movieParserClass = get_class($movieParser);
        // 对象实例
        $this->movieParserInstances[$movieParserClass] = $movieParser;

        // 类字符串
        $this->movieParsers[$movieParserClass] = $params;
        return $this;
    }

    public function getMovieParser(string $movieParser): ?array
    {
        return $this->movieParsers[$movieParser] ?? null;
    }

    public function hasMovieParser(string $movieParser): bool
    {
        // isset
        return array_key_exists($movieParser, $this->movieParsers);
    }

    protected function targetTsQueue(): void
    {
        $progressBar = $this->container->get('bar');
        $color = $this->container->get('color');
        /**
         * @var $registry Registry
         */
        $registry = $progressBar->getRegistry();
        $registry->setValue('max', $this->m3u8Sum);
        $progressBar->setRegistry($registry);

        echo $color("Loading multiple M3U8 files：")->white()->bold()->highlight('yellow') . PHP_EOL . PHP_EOL;
        foreach ($this->movieParsers as $movieParserClass => $m3u8List) {
            $movieParserInstance = $this->getMovieParserInstance($movieParserClass);
            if (empty($movieParserInstance)) {
                continue;
            }

            foreach ($m3u8List as $m3u8) {
                $tsQueue = $movieParserInstance->setM3u8($m3u8)->runParser();
                if (empty($tsQueue)) {
                    $this->failQueue[$movieParserClass][] = $m3u8;
                } elseif ($tsQueue instanceof \SplQueue) {
                    $progressBar->advance();
                    $this->targetQueue[$movieParserClass][] = $tsQueue;
                }
            }
        }
        echo PHP_EOL;
        echo $color("Successfully analyzed ( {$this->m3u8Sum} ) files and started downloading ......")
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

    protected function getMkdirDirectory($movieParserClass, $index)
    {
        if($this->hasMovieParser($movieParserClass)) {
           $m3u8s = $this->getMovieParser($movieParserClass);
           $filename  = basename($m3u8s[$index]);
           return str_replace(".m3u8", "", $filename);
        }

        throw new \RuntimeException("Parser {$movieParserClass} is not defined.");
    }

    public function run()
    {
        $this->targetTsQueue();

        \Co\run(function () {
            \Swoole\Runtime::enableCoroutine(SWOOLE_HOOK_ALL | SWOOLE_HOOK_CURL);

            foreach ($this->targetQueue as $movieParserClass => $arrayQueue) {

                /**
                 * @var $splQueue \SplQueue
                 */
                foreach ($arrayQueue as $index => $splQueue) {
                    $splQueueCount = $splQueue->count();

                    $directory = $this->getMkdirDirectory($movieParserClass,$index);
                    $output = $this->config['output'] . "/{$directory}{$index}";

                    $filename = "{$directory}{$index}.mp4";
                    if (is_file("{$output}/$filename")) {
                        $this->historyComplete[$filename] = [];
                        continue;
                    }

                    echo "Downloading file {$directory}#{$index}:" . PHP_EOL;

                    if (!Utils::isDir($output)) {
                        Utils::mkdirDiectory($output);
                    }

                    $channel = new Channel($this->config['concurrent']);

                    /**
                     * @var $progressBar Manager
                     */
                    $progressBar = $this->container->get('bar');
                    $registery = $progressBar->getRegistry();
                    $registery->setValue('max', $splQueueCount);
                    $progressBar->setRegistry($registery);

                    for ($index = 0; $index < $splQueueCount; $index++) {
                        $targetTs = "{$output}/{$index}.ts";
                        clearstatcache(
                            dirname($targetTs),
                            $targetTs
                        );
                        if (is_file($targetTs)) {
                            $this->historyComplete[$filename][$index] = $targetTs;
                            $progressBar->advance();
                            continue;
                        }

                        $channel->push(true);
                        Coroutine::create(function () use (
                            $splQueue,
                            $output,
                            $progressBar,
                            $index,
                            $channel,
                            $registery,
                            $filename,
                            $targetTs
                        ) {
                            defer(function () use ($channel) {
                                $channel->pop();
                            });

                            $remoteTs = $splQueue->shift();

                            $this->client->get()->request($remoteTs);
                            if ($this->client->isSucceed() && $this->client->getBodySize() > 2048) {
                                $this->historyComplete[$filename][$index] = $targetTs;
                                Utils::writeFile($targetTs, $this->client->getBody());
                                $progressBar->advance();
                            }
                        });
                    }

                    // 任务结束
                    for ($concurrent = $this->config['concurrent']; $concurrent--;) {
                        $channel->push(true);
                    }
                    $channel->close();

                    // 开始合并
                    $this->runMergedFile($filename, $output, $splQueueCount);

                    $color = $this->container->get('color');

                    echo PHP_EOL;
                    echo $color("Download ( {$filename} ) file complete.")
                            ->white()
                            ->bold()
                            ->highlight('green') . PHP_EOL . PHP_EOL;
                }
            }

            $this->resultStatistics();

        });
    }

    protected function resultStatistics()
    {
        $success = $this->sucessCount();
        $error = $this->m3u8Sum - $success;
        $color = $this->container->get('color');
        $files = $this->successFiles();

        echo PHP_EOL;
        echo $color('完成任务统计: ')->white()->bold() . PHP_EOL . PHP_EOL;
        $success = $color($success)->white()->bold()->highlight('green');
        echo $color("成功数量: {$success} 个, 失败数量: ")->white()->bold();
        $error = $color($error)->white()->bold()->highlight('red');
        echo $color("{$error} 个.")->white()->bold() . PHP_EOL . PHP_EOL;

        echo $color('已成功文件记录: ( ')->white()->bold();
        echo $color("{$files}")->white()->bold()->highlight('green');
        echo $color(' ).')->white()->bold() . PHP_EOL . PHP_EOL;

    }

    protected function successFiles()
    {
        return implode(', ', array_keys($this->historyComplete));
    }

    protected function sucessCount()
    {
        return count(array_keys($this->historyComplete));
    }

    /**
     * 合并下载文件
     * @param $filename string
     * @param $output string
     * @param $splQueueCount string
     */
    protected function runMergedFile(string $filename, string $output, $splQueueCount)
    {
        if (count($this->historyComplete[$filename]) === $splQueueCount) {
            $mp4File = "{$output}/$filename";

            clearstatcache();
            $outputPath = realpath($output);
            echo "Merging files：{$outputPath}.mp4" . PHP_EOL;

            /**
             * @var $bar Manager
             */
            $progress = $this->container->get('bar');
            /**
             * @var $registery Registry
             */
            $registery = $progress->getRegistry();
            $registery->setValue('max', $splQueueCount);
            $progress->setRegistry($registery);

            if (isset($this->historyComplete[$filename])) {
                $fileList = $this->historyComplete[$filename];

                ksort($fileList);

                foreach ($fileList as $index => $file) {
                    clearstatcache();
                    if (is_file($file)) {
                        $data = file_get_contents($file);
                        file_put_contents($mp4File, $data, FILE_APPEND);
                        unlink($file);
                        $progress->advance();
                    }
                }
            }
        }
        echo PHP_EOL;
    }
}