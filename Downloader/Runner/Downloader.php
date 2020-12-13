<?php declare(strict_types=1);

namespace Downloader\Runner;

use Co\Channel;
use Downloader\Runner\Decrypt\DecryptionInterface;
use ProgressBar\Manager;
use ProgressBar\Registry;
use Psr\Container\ContainerInterface;
use Swoole\Coroutine;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Downloader
{
    /**
     * 版本号
     */
    const VERSION = '1.0.1';

    // 下载超时
    const DOWNLOAD_TIMEOUT = 600;

    /**
     * 解析器
     * @var $movieParsers array
     */
    protected $movieParsers = [];

    /**
     * 解析器对象
     * @var $movieParsers array
     */
    protected $movieParserInstances = [];

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
     * @var array $videoUrls
     */
    protected $videoUrls = [];

    /**
     * 解密接口实例列表
     * @var array $decrptInterface
     */
    protected $decrptInterface = [];

    /**
     *
     * @var OutputInterface $outputConsole
     */
    protected $outputConsole = null;

    /**
     * @var InputInterface $inputConsole
     */
    protected $inputConsole = null;

    /**
     * @var array $config
     */
    protected $config = [
        'output' => '',
        'concurrent' => 25,
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
        $this->config        = array_merge($this->config, $config);
        $this->container     = $container;
        $this->inputConsole  = $config['inputConsole'] ?? null;
        $this->outputConsole = $config['outputConsole'] ?? null;

        if (empty($this->inputConsole) || !$this->inputConsole instanceof InputInterface) {
            throw new \InvalidArgumentException("InputInterface is not implemented.");
        }

        if (empty($this->outputConsole) || !$this->outputConsole instanceof OutputInterface) {
            throw new \InvalidArgumentException("OutputInterface is not implemented.");
        }

        $this->config['outputConsole'] = $this->outputConsole;
        $this->config['inputConsole']  = $this->inputConsole;

        $this->outputConsole->write(PHP_EOL);
        $this->outputConsole->writeln(">> <comment>" . $this->baseInfo() . "</comment>");
        $this->outputConsole->write(PHP_EOL);
        $this->outputConsole->writeln(
            ">> <fg=black;bg=green>Downloader-M3U8 started successfully!</>"
        );
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
        $this->outputConsole->write(PHP_EOL);
        $this->outputConsole->writeln(">> <fg=white>Retrieving remote file information: </>");
        $this->outputConsole->write(PHP_EOL);

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
                ->setInputConsole($this->inputConsole)
                ->setOuputConsole($this->outputConsole)
                ->setMovieParserClass($movieParserClass)
                ->runParser();

            /**
             * @var $m3u8File M3u8File
             */
            foreach ($m3u8Files as $index => $m3u8File) {
                if ($m3u8File instanceof M3u8File) {
                    // 请求成功
                    $this->groups[$movieParserClass][] = $m3u8File;
                }
            }
        }
        $this->outputConsole->write(PHP_EOL);
        $this->outputConsole->writeln(">> <fg=black;bg=green>Get ({$this->groupM3u8Sum}) tasks, start downloading: </>");
        $this->outputConsole->write(PHP_EOL);
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

    public function start()
    {
        $this->startParsingFile();

        foreach ($this->groups as $movieParserClass => $m3u8Files) {
            /**
             * @var $m3u8File M3u8File
             */
            foreach ($m3u8Files as $index => $m3u8File) {
                $filename = $m3u8File->getM3u8Id();
                $basename = $m3u8File->getBasename();
                $output   = $m3u8File->getOutput();

                clearstatcache();
                if (is_file($filename)) {
                    // 成功文件记录
                    $this->success[]                 = $basename;
                    // 成功文件完整路径记录
                    $this->videoUrls[md5($basename)] = realpath($filename);
                    $this->outputConsole->writeln(">> <fg=green>Download file ({$basename}) already exists locally! </>");
                    continue;
                }

                if (!Utils::mkdirDiectory($output)) {
                    throw new \RuntimeException("Mkdir fail, dir is:{$output}.");
                }

                $this->outputConsole->writeln(">> <fg=yellow>Downloading (#{$index}) file [ {$basename} ]: </>");
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

        $this->statisticsTable();
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
        $registery   = $progressBar->getRegistry();
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

            $basename = basename($remoteTs);
            $targetTs = "{$output}{$basename}";
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
        $output       = $m3u8File->getOutput();
        $tsCount      = $m3u8File->getTsCount();
        $video        = $m3u8File->getM3u8Id();
        $splArray     = $m3u8File->getMergedTsArray();
        $wg           = $m3u8File->getChannel();
        $bindMap      = $m3u8File->getBindTsMap();
        $tsTotalCount = $splArray->getSize();

        $flag = true;

        // 失败任务重试
        if ($this->successStatistics) {
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

                if (isset($bindMap[$basename])) {
                    // 失败任务重试
                    $remoteTs = $bindMap[$basename];
                    //
                    $this->container->get('log')->record("retry task, remote_ts=" . $remoteTs);
                    $this->singleTaskDownload($m3u8File, $progressBar, $wg, $remoteTs);
                }
            }

            if ($flag === false) {
                $m3u8File->closedChannel();
            }
        }

        // 下载超时10分钟退出
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
                $this->outputConsole->writeln(">> <info>Saving files：[ {$relpath}.mp4 ]</info>");

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

                $count = 0;
                foreach ($splArray as $index => $file) {
                    $videoFullPath = "{$output}/{$file}";
                    clearstatcache();
                    if (is_file($videoFullPath)) {
                        $count++;
                        $data = file_get_contents($videoFullPath);
                        Utils::writeFile($video, $data, true);
                        unlink($videoFullPath);
                        $progressBar->advance();
                    }
                }

                if ($splArray->count() === $count)
                {
                    // 成功文件记录
                    $this->success[]                 = $basename;
                    // 成功文件完整路径记录
                    $this->videoUrls[md5($basename)] = realpath($video);

                    // 删除成功统计数据
                    $this->successStatistics = [];

                    $this->outputConsole->writeln(">> <fg=black;bg=green>Download [ {$basename} ] file complete!</>");
                } else {
                    // todo : write fail
                }
                break;
            } elseif ((time() - $timeout) > static::DOWNLOAD_TIMEOUT) {
                $this->outputConsole->write(PHP_EOL.PHP_EOL);
                $this->outputConsole->writeln(">> <error>File < {$basename} > download timed out.</error>");
                break;
            } else {
                // 0.5 秒运行一次上面的代码
                Coroutine::sleep(0.5);
            }
        }

        $this->outputConsole->write(PHP_EOL.PHP_EOL);
    }

    protected function statisticsTable()
    {
        $successFiles = $this->successFiles();
        $m3u8Count    = $this->groupM3u8Sum;
        $groupCount   = count($this->groups);

        $this->outputConsole->writeln(">> <info>Download statistics:</info>");

        $table = new Table($this->outputConsole);
        $table->setHeaders(array('no', 'filename',  'file_size', 'status', 'group_count', 'file_count'));

        $rows = [];
        $fileSize = "0B";
        foreach ($successFiles as $id => $m3u8Filename)
        {
            if (in_array($m3u8Filename, $this->success, true))
            {
                $md5Value = md5($m3u8Filename);
                if (isset($this->videoUrls[$md5Value]))
                {
                    $fileSize = Utils::fileSize(filesize($this->videoUrls[$md5Value]));
                }

                $rows[] = array(
                    $id, $m3u8Filename, $fileSize, '<info>succeed</info>', $groupCount, $m3u8Count,
                );
                continue;
            }
            $rows[] = array(
                $id, $m3u8Filename, $fileSize, '<error>fail</error>', $groupCount, $m3u8Count,
            );
        }
        $table->setRows($rows);
        $table->render();
    }


    protected function successFiles() : array
    {
        $groups = [];
        foreach ($this->groups as $group)
        {
            /**
             * @var M3u8File $m3u8
             */
          foreach ( $group as $m3u8)
          {
              $groups[] = $m3u8->getBasename();
          }
        }
        return $groups;
    }

    public function baseInfo(): string
    {
        $information = sprintf("StartTime: %s, Os: %s, Swoole: %s, PHP: %s",
            date('Y-m-d H:i:s'),
            PHP_OS,
            SWOOLE_VERSION,
            phpversion()
        );

        return $information;
    }
}