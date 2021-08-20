<?php declare(strict_types=1);
namespace Downloader\Runner;

use Downloader\Runner\Middleware\Data\Mu38Data;
use League\Pipeline\PipelineInterface;
use League\Pipeline\StageInterface;
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
     * Container
     * @var ContainerInterface $container
     */
    protected $container;

    /**
     * 视频文件总数
     * @var int $groupM3u8Sum
     */
    protected $groupM3u8Sum   = 0;

    /**
     * ts
     * @var array $succeed
     */
    protected static $succeed  = [];

    /**
     *  fail
     * @var array $fail
     */
    protected static $fail = [];

    /**
     * m3u8 succeed
     * @var array $m3u8Succeed
     */
    protected static $m3u8Succeed = [];

    /**
     * @var array $statistics
     */
    protected static $statistics = [];

    /**
     * @var array $decryptMiddleware
     */
    protected $decryptMiddleware = [];

    /**
     *
     * @var OutputInterface $outputConsole
     */
    protected $outputConsole  = null;

    /**
     * @var InputInterface $inputConsole
     */
    protected $inputConsole   = null;

    /**
     * @var array $config
     */
    protected $config = [ 'output' => '', 'concurrent'  => 25 ];

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
     * @param array $middleware 解密中间件
     * @return $this
     */
    public function setMovieParser(MovieParser $movieParser, array $params, array $middleware = [])
    {
        // check urls
        foreach ($params as $param) {
            if ($this->hasLegality($param)) {
                throw new AddressLegalityException($param);
            }
        }
        $this->groupM3u8Sum += count($params);

        // check middleware.
        foreach ($middleware as $value)
        {
            if (!$value instanceof StageInterface)
            {
                throw new \LogicException("{$value} invalid middleware.");
            }
        }

        $movieParser->setContainer($this->container);
        $movieParserClass = get_class($movieParser);

        if (isset($this->movieParserInstances[$movieParserClass])) {
            throw new MovieParserInstancesException("Parser may be overwritten, MovieParser is: {$movieParserClass}.");
        }
        // 对象实例
        $this->movieParserInstances[$movieParserClass] = $movieParser;
        $this->decryptMiddleware[$movieParserClass] = $middleware;

        // 类字符串
        $this->movieParsers[$movieParserClass] = $params;
        return $this;
    }

    protected function m3u8Files(): array
    {
        $this->outputConsole->write(PHP_EOL);
        $this->outputConsole->writeln(">> <fg=white>Searching task data: </>");
        $this->outputConsole->write(PHP_EOL);

        // m3u8 files
        $data = [];

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
                ->setDecryptMiddleware($this->decryptMiddleware[$movieParserClass])
                ->setConfig($this->config)
                ->setInputConsole($this->inputConsole)
                ->setOutputConsole($this->outputConsole)
                ->setMovieParserClass($movieParserClass)
                ->runParser();

            /**
             * @var $m3u8File M3u8File
             */
            foreach ($m3u8Files as $index => $m3u8File) {
                if ($m3u8File instanceof M3u8File) {
                    // 请求成功
                    $data[$movieParserClass][] = $m3u8File;
                }
            }
        }

        if (empty($data))
        {
            $this->outputConsole->writeln(">> <fg=black;bg=yellow>No task found! </>");
        } else {
            $this->outputConsole->write(PHP_EOL);
            $this->outputConsole->writeln(">> <fg=black;bg=green>Found ({$this->groupM3u8Sum}) tasks.  </>");
            $this->outputConsole->write(PHP_EOL);
        }

        return $data;
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
        foreach ($this->m3u8Files() as $movieParserClass => $m3u8Files) {
            /**
             * @var $m3u8File M3u8File
             */
            foreach ($m3u8Files as $id => $m3u8File) {
                $filename = $m3u8File->getM3u8Id();
                $output   = $m3u8File->getOutput();
                $basename = $m3u8File->getBasename();
                $hashId   = $m3u8File->getHashId();
                $count    = $m3u8File->getTsCount();

                // m3u8 file exists.
                clearstatcache(true);
                if (is_file($filename)) {
                    // m3u8 file succeed.
                    static::$m3u8Succeed[$hashId][] = $basename;
                    static::setSuccess($basename, $filename, $count);
                    $this->outputConsole->writeln(">> <fg=green>[{$basename} - {$id}] file found on the local disk! </>");
                    $this->outputConsole->write(PHP_EOL);
                    continue;
                }

                if (!Utils::mkdirDirectory($output)) {
                    throw new \RuntimeException("Mkdir fail, dir is:{$output}.");
                }

                $this->outputConsole->writeln(">> <fg=yellow>Download task [ {$basename} - {$id} ]: </>");
                try {
                    if(
                        // Merged fail.
                        ($failNum = $this->downloadM3u8Video($m3u8File) ) === 0  &&
                        // Download fail.
                        ($failNum = $this->mergedTsFragment($m3u8File)) === 0
                    ) {
                        static::$m3u8Succeed[$hashId][] = $basename;
                        static::setSuccess($basename, $filename, $count);
                    } else {
                        static::setFail($basename, $failNum);
                        static::$fail[$hashId][] = $basename;
                        $this->outputConsole->write(PHP_EOL);
                        $this->outputConsole->writeln(">> <error>Download error ({$basename}) ! </error>");
                        $this->outputConsole->write(PHP_EOL);
                    }
                    // statistics info.
                    $this->outputConsole->writeln(">> <info>Task list:</info>");
                    $this->statisticsTable();
                } catch (\Throwable $e) {
                    $this->container->get('log')->record($e);
                }
            }
        }
    }

    public static function setSuccess($basename, $filename, $count) {
        static::$statistics[$basename] = array(
            'basename' => $basename,
            'size'     => Utils::fileSize(filesize($filename)),
            'status'   => '<info>succeed</info>', // success
            'fail_num'   => $count
        );
    }

    public static function setFail($basename, $fails)
    {
        static::$statistics[$basename] = array(
            'basename' => $basename,
            'size'     => '0B',
            'status'   => '<error>fail</error>', // fail
            'fail_num'   => "<error>{$fails}</error>"
        );
    }

    /**
     * 开始下载任务M3u8文件
     * @param M3u8File $m3u8File
     * @return int
     */
    protected function downloadM3u8Video(M3u8File $m3u8File): int
    {
        $tsCount  = $m3u8File->getTsCount();
        $wg       = $m3u8File->getChannel();
        $splQueue = $m3u8File->getSplQueue();
        $hashId   = $m3u8File->getHashId();

        /**
         * @var $progressBar Manager
         */
        $progressBar = $this->container->get('bar');
        $registry    = $progressBar->getRegistry();
        $registry->setValue('max', $tsCount);
        $progressBar->setRegistry($registry);

        while (!$splQueue->isEmpty() && $remoteTs = $splQueue->shift()) {
            $this->downloadTsFragment($m3u8File, $progressBar, $wg, $remoteTs);
        }
        $m3u8File->closedChannel();

        // download failed
        return count(static::$fail[$hashId] ?? []);
    }

    // downloading ts .....
    protected function downloadTsFragment(M3u8File $m3u8File, Manager $progressBar, Coroutine\Channel $wg, string $remoteTs)
    {
        // Single process statistics.
        $output     = $m3u8File->getOutput();
        $basename   = basename($remoteTs);
        $hashId     = $m3u8File->getHashId();
        $targetTs   = "{$output}/{$basename}";

        // ts file exists.
        clearstatcache(true, $targetTs);
        if (is_file($targetTs)) {
            static::$succeed[$hashId][] = $basename;
            $progressBar->advance();
            return;
        }

        // download ts file.
        $wg->push(true);
        Coroutine::create
        (
            [$this, 'coDownload'],
            $progressBar,
            $wg,
            $m3u8File,
            $remoteTs,
            $targetTs,
            $basename,
            $hashId
        );
    }

    public function coDownload($progressBar, $wg, $m3u8File, $remoteTs, $targetTs, $basename, $hashId)
    {
        \Swoole\Coroutine::defer(function () use ($wg) {
            // 5s exited coroutine.
            $wg->pop(5);
        });

        try {
            /**
             * @var HttpClient $client
             */
            $client = $this->container->get('client');

            // request ts file.
            $client->get()->request($remoteTs);

            // > 2mb ts file size.
            if ($client->getBodySize() > 2 * 1024) {
                $data = $client->getBody();

                // decrypt ts file.
                if ($middleware = $m3u8File->getDecryptMiddleware())
                {
                    /**
                     * @var $pipeline PipelineInterface
                     */
                    $pipeline = $this->container->get('middleware');

                    foreach ($middleware as $value)
                    {
                        $pipeline = $pipeline->pipe($value);
                    }
                    $data = $pipeline->process(new Mu38Data($m3u8File->getAuthKey(), $data));
                }

                if ($data) {
                    static::$succeed[$hashId][] = $basename;
                    Utils::writeFile($targetTs, $data);
                    $progressBar->advance();
                }
                return;
            }
            // fail ts file.
            static::$fail[$hashId][] = $remoteTs;

        } catch (RetryRequestException $e) {
            $this->container->get('log')->record($e);
            // fail ts file.
            static::$fail[$hashId][] = $remoteTs;
        }
    }

    /**
     * 开始合并下载任务,返回文件失败数量
     * @param M3u8File $m3u8File
     * @return int
     */
    protected function mergedTsFragment(M3u8File $m3u8File) :int
    {
        $output       = $m3u8File->getOutput();
        $tsCount      = $m3u8File->getTsCount();
        $videoFile    = $m3u8File->getM3u8Id();
        $splArray     = $m3u8File->getMergedTsArray();

        $successes    = count(static::$succeed[$m3u8File->getHashId()] ?? []);
        $basename     = $m3u8File->getBasename();

        if ($successes === $splArray->count())
        {
            $realpath = realpath($output);
            $this->outputConsole->writeln(">> <info>Writing file: [ {$realpath}.mp4 ]</info>");

            /**
             * @var $progressBar Manager
             */
            $progressBar = $this->container->get('bar');

            /**
             * @var $registry Registry
             */
            $registry = $progressBar->getRegistry();
            $registry->setValue('max', $tsCount);
            $progressBar->setRegistry($registry);

            $count = 0;
            foreach ($splArray as $tsFile) {
                $ts = "{$output}/{$tsFile}";
                clearstatcache(true, $ts);
                if (is_file($ts)) {
                    $count++;
                    $data = file_get_contents($ts);
                    Utils::writeFile($videoFile, $data, true);
                    @unlink($ts);
                    $progressBar->advance();
                    static::setSuccess($basename, $videoFile, $count);
                }
            }

            // Merger complete.
            if ($splArray->count() === $count)
            {
                // 成功文件记录
                static::$m3u8Succeed[]             = $basename;

                $this->outputConsole->writeln(">> <fg=black;bg=green>Download task [ {$basename} ] completed!</>");
                // println
                $this->outputConsole->write(PHP_EOL.PHP_EOL);

                return 0;
            }
            // Merge failed. write log .
        }
        // Download failed.
        return $splArray->count() - $successes;
    }

    protected function statisticsTable()
    {
        $table = new Table($this->outputConsole);
        $table->setHeaders(array('编号', '文件名称',  '文件大小', '下载状态', '分片总量'));

        $id = 0;
        foreach (static::$statistics as $key => $value)
        {
           $id++;
           array_unshift($value, $id);
           static::$statistics[$key] = $value;
        }

        $table->setRows(static::$statistics);
        $table->render();
    }

    public function baseInfo(): string
    {
        $base = sprintf(
            "Downloader-M3U8: %s, Os: %s, Swoole: %s, PHP: %s.",
            date('Y-m-d H:i:s'),
            PHP_OS, SWOOLE_VERSION, phpversion()
        );

        return $base;
    }
}
