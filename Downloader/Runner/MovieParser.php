<?php declare(strict_types=1);

namespace Downloader\Runner;

use Co\Channel;
use Swoole\Coroutine\WaitGroup;
use ProgressBar\Manager;
use ProgressBar\Registry;
use Psr\Container\ContainerInterface;
use Swoole\Coroutine;
use Symfony\Component\Console\Output\OutputInterface;

abstract class MovieParser
{

    protected const DOWNLOAD_FILE_MAX = 1024;

    /**
     * 每组M3u8文件
     * @var $m3u8Files array
     */
    protected $m3u8Files = [];

    /**
     * 每组ts队列
     * @var array
     */
    protected $m3u8Urls;

    /**
     * @var $container ContainerInterface
     */
    protected $container;

    /**
     * 解密中间件集合
     * @var array $decryptMiddleware
     */
    protected $decryptMiddleware;

    /**
     * @var $movieParserClass
     */
    protected $movieParserClass;

    /**
     * Downloader 配置文件
     * @var array $config
     */
    protected $config = [];

    /**
     * @var OutputInterface $outputConsole
     */
    protected $outputConsole;

    /**
     * @var $inputConsole
     */
    protected $inputConsole;

    /**
     * @param $m3u8Url string 视频文件信息
     * @param $movieTs  string ts文件名称
     * @return string  返回完整ts视频地址
     */
    abstract protected function parsedTsUrl(string $m3u8Url, string $movieTs): string;


    /**
     * 解析KEY
     * @param string $indexM3u8
     * @return string
     */
    protected function authKey(string $indexM3u8): string
    {
        $authKey = '';

        if (preg_match('#\#EXT-X-KEY:METHOD=(.*?)#Ui', $indexM3u8, $matches)) {
            $authKey = $matches[1] ?? '';
        }
        return $authKey;
    }

    public function runParser(): ?array
    {
        $wg = new WaitGroup();

        $progressBar = $this->container->get('bar');
        /**
         * @var $registry Registry
         */
        $registry = $progressBar->getRegistry();
        $registry->setValue('max', count($this->m3u8Urls));
        $progressBar->setRegistry($registry);

        foreach ($this->m3u8Urls as $index => $m3u8Url) {
            $wg->add();
            Coroutine::create
            (
                '\\Downloader\\Runner\\MovieParser::m3u8Object',
                $wg,
                $m3u8Url,
                $progressBar,
                $index
            );
        }
        if ($wg->wait(60) === false) {
            throw new \RuntimeException(
                'Download timeout, failed file information::' . var_export($this->m3u8Urls, true)
            );
        }
        return $this->m3u8Files;
    }

    public function m3u8Object(WaitGroup $wg, $m3u8Url, Manager $progressBar, $index)
    {
        \Swoole\Coroutine::defer(function () use ($wg, $progressBar) {
            $wg->done();
        });

        /**
         * @var $client HttpClient
         */
        $client = $this->container->get('client');

        try {
            $client->get()->request(trim($m3u8Url));

            if ($client->getBodySize() > self::DOWNLOAD_FILE_MAX) {
                // 初始化变量
                $tsBindMap = [];
                $splQueue = new \SplQueue();

                $m3u8File = new M3u8File();
                $this->m3u8Files[$index] = $m3u8File;

                // 下载的内容数据
                $data = $client->getBody();

                // Get ts list from M3U8 file.
                if ($tsUrls = $this->readTSFileUrls($data)) {
                    $splArray = new \SplFixedArray(count($tsUrls));
                    foreach ($tsUrls as $id => $ts) {
                        // 完整ts地址
                        $fromTsFile = trim($ts) . '.ts';
                        $tsUrl = $this->parsedTsUrl($m3u8Url, $fromTsFile);
                        $splQueue->add($id, $tsUrl);
                        $basename = basename($tsUrl);
                        $tsBindMap[$basename] = $tsUrl;
                        $splArray[$id] = $basename;
                    }

                    // M3u8File Object.
                    $m3u8File->setAuthKey($authKey = $this->authKey($data));
                    $m3u8File->setSplQueue($splQueue);
                    $m3u8File->setGroupId($this->movieParserClass);
                    $m3u8File->setOutput($this->getOutputDir());
                    $m3u8File->setM3u8Id($index);
                    $m3u8File->setTsCount($splQueue->count());
                    $m3u8File->setChannel(new \Swoole\Coroutine\Channel($this->getConcurrentNumber()));
                    $m3u8File->setMergedTsArray($splArray);
                    $m3u8File->setConcurrent($this->getConcurrentNumber());
                    $m3u8File->setBindTsMap($tsBindMap);
                    $m3u8File->setM3u8UrlData($data);
                    $m3u8File->setDecryptMiddleware($this->decryptMiddleware);
                    // add progressbar:1
                    $progressBar->advance();
                }
            }
        } catch (RetryRequestException $e) {
            $this->container->get('log')->record($e);
        }
    }

    /**
     * @param $data
     * @return array
     */
    protected function readTSFileUrls($data): array
    {
        preg_match_all("#,\s(.*?)\.ts#is", $data, $matches);
        return $matches[1] ?? [];
    }

    private function getConcurrentNumber()
    {
        $concurrent = intval($this->config['concurrent'] ?? '15');

        return $concurrent < 1 ? 15 : $concurrent;
    }

    protected function getOutputDir()
    {
        $outputDir = $this->config['output'];
        if (substr($outputDir, -1) === DIRECTORY_SEPARATOR) {
            return $outputDir . $this->getGroupName() . DIRECTORY_SEPARATOR;
        }

        return $outputDir . DIRECTORY_SEPARATOR . $this->getGroupName() . DIRECTORY_SEPARATOR;
    }

    protected function getGroupName()
    {
        return substr(md5($this->movieParserClass), 32 - 9);
    }

    public function setMovieParserClass($movieParserClass)
    {
        $this->movieParserClass = $movieParserClass;
        return $this;
    }

    /**
     * @param array $m3u8Urls
     * @return MovieParser
     */
    public function setM3u8s(array $m3u8Urls): MovieParser
    {
        $this->m3u8Urls = $m3u8Urls;
        return $this;
    }

    /**
     * @param array $decryptMiddleware
     * @return $this
     */
    public function setDecryptMiddleware(array $decryptMiddleware)
    {
        $this->decryptMiddleware = $decryptMiddleware;
        return $this;
    }

    /**
     * @param $outputConsole
     * @return $this
     */
    public function setOutputConsole($outputConsole)
    {
        $this->outputConsole = $outputConsole;
        return $this;
    }

    public function setInputConsole($inputConsole)
    {
        $this->inputConsole = $inputConsole;
        return $this;
    }

    public function setConfig($config)
    {
        $this->config = $config;
        return $this;
    }

    public function setContainer(ContainerInterface $container): MovieParser
    {
        $this->container = $container;
        return $this;
    }
}