<?php declare(strict_types=1);

namespace Downloader\Runner;

use Psr\Container\ContainerInterface;

abstract class MovieParser
{
    /**
     * ts 已分析完成的队列
     * @var $splQueue \SplQueue
     */
    protected $splQueue = null;

    /**
     * M3U8目标地址
     * @var string
     */
    protected $m3u8Url;

    /**
     * @var $container ContainerInterface
     */
    protected $container;

    public function setM3u8($m3u8): MovieParser
    {
        $this->m3u8Url = $m3u8;
        return $this;
    }

    public function setContainer(ContainerInterface $container): MovieParser
    {
        $this->container = $container;
        return $this;
    }

    /**
     * @param $m3u8Url string 视频文件信息
     * @param $movieTs  string ts文件名称
     * @return string  返回完整ts视频地址
     */
    abstract protected function parsedTsUrl(string $m3u8Url, string $movieTs): string;

    public function runParser() : ?\SplQueue
    {
        /**
         * @var $client HttpClient
         */
        $client = $this->container->get('client');
        $client->get()->request($this->m3u8Url);
        $data = $client->getBody();

        if ($client->isSucceed() && strlen($data) > 50)
        {
            // make queue
            $this->splQueue = new \SplQueue();

            preg_match_all("#,\s(.*?)\.ts#is", $data, $matches);
            foreach ($matches[1] as $id => $ts) {
                // 完整ts地址
                $fromTsFile = trim($ts) . '.ts';
                $tsUrl = $this->parsedTsUrl($this->m3u8Url, $fromTsFile);

                $this->splQueue->add($id,$tsUrl);
            }
        }
        return $this->splQueue;
    }

    public function getSplQueue()
    {
        return $this->splQueue;
    }
}