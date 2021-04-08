<?php
namespace Downloader\Runner;

use Co\Channel;

class M3u8File
{
    /**
     * 文件名称ID
     * @var $m3u8Id string
     */
    protected $m3u8Id = '';

    /**
     * 导出目录
     * @var $output string
     */
    protected $output = '';

    /**
     * m3u8文件ts总数量
     * @var \SplQueue $splQueue
     */
    protected $splQueue;

    /**
     * 解密中间件
     * @var $decryptMiddleware array
     */
    protected $decryptMiddleware;

    /**
     * m3u8url 数据内容
     * @var $m3u8UrlData string
     */
    protected $m3u8UrlData;

    /**
     * 分组ID
     * @var string $groupId
     */
    protected $groupId = '';

    /**
     * @var string $suffix
     */
    protected $suffix = 'mp4';

    /**
     * @var \SplFixedArray $mergedTs
     */
    protected $mergedTsArray;

    /**
     * @var int $tsCount
     */
    protected $tsCount  = 0;

    /**
     * @var null|Channel;
     */
    protected $channel = null;

    /**
     * @var int $concurrent
     */
    protected $concurrent = 0;

    /**
     * basename:remote_ts, .....
     * @var array $tsMap
     */
    protected $bindTsMap = [];

    /**
     * @var string $authKey
     */
    protected $authKey;

    public function setAuthKey(string $authKey) : void
    {
        $this->authKey = $authKey;
    }

    public function getAuthKey() : string
    {
       return $this->authKey;
    }

    /**
     * 获取解密中间件集合
     * @return array
     */
    public function getDecryptMiddleware(): array
    {
        return $this->decryptMiddleware;
    }

    /**
     * @param array $decryptMiddleware
     */
    public function setDecryptMiddleware(array $decryptMiddleware): void
    {
        $this->decryptMiddleware = $decryptMiddleware;
    }

    /**
     * @return string
     */
    public function getM3u8UrlData(): string
    {
        return $this->m3u8UrlData;
    }

    /**
     * @param string $m3u8UrlData
     */
    public function setM3u8UrlData(string $m3u8UrlData): void
    {
        $this->m3u8UrlData = $m3u8UrlData;
    }

    /**
     * @return array
     */
    public function getBindTsMap(): array
    {
        return $this->bindTsMap;
    }

    /**
     * @param array $bindTsMap
     */
    public function setBindTsMap(array $bindTsMap): void
    {
        $this->bindTsMap = $bindTsMap;
    }

    /**
     * @return \SplFixedArray
     */
    public function getMergedTsArray(): \SplFixedArray
    {
        return  $this->mergedTsArray;
    }

    /**
     * @param \SplFixedArray $mergedTsArray
     */
    public function setMergedTsArray(\SplFixedArray $mergedTsArray): void
    {
        $this->mergedTsArray = $mergedTsArray;
    }

    /**
     * @return string
     */
    public function getM3u8Id(): string
    {
        return $this->m3u8Id;
    }

    /**
     * @param int $index
     */
    public function setM3u8Id(int $index = 0): void
    {
        if ($index >= 0) {
            $basename = basename($this->output);
            $m3u8Id = $this->output . "{$index}_{$basename}.{$this->suffix}";

            $this->m3u8Id = $m3u8Id;
            return;
        }
        $this->m3u8Id = $this->output . date('YmdHis') . ".{$this->suffix}";
    }

    /**
     * @param $suffix string
     */
    public function setSuffix($suffix): void
    {
        $this->suffix = $suffix;
    }

    /**
     * @return string
     */
    public function getOutput(): string
    {
        return $this->output;
    }

    /**
     * @param string $output
     */
    public function setOutput(string $output): void
    {
        if (empty($output))
        {
            throw new \InvalidArgumentException("Invalid path: {$output}.");
        }

        $this->output = $output;
    }

    /**
     * @return string
     */
    public function getBasename(): string
    {
        return basename($this->m3u8Id);
    }

    /**
     * @return string
     */
    public function getHashId()
    {
        return md5(basename($this->m3u8Id));
    }

    /**
     * @return \SplQueue
     */
    public function getSplQueue(): \SplQueue
    {
        return $this->splQueue;
    }

    /**
     * @param \SplQueue $splQueue
     */
    public function setSplQueue(\SplQueue $splQueue): void
    {
        $this->splQueue = $splQueue;
    }

    /**
     * @return string
     */
    public function getGroupId(): string
    {
        return $this->groupId;
    }

    /**
     * @param string $groupId
     */
    public function setGroupId(string $groupId): void
    {
        $this->groupId = $groupId;
    }

    /**
     * @return int
     */
    public function getTsCount(): int
    {
        return $this->tsCount;
    }

    /**
     * @param int $tsCount
     * @return int
     */
    public function setTsCount(int $tsCount): int
    {
        return $this->tsCount = $tsCount;
    }

    /**
     * @return Channel|null
     */
    public function getChannel(): ?\Swoole\Coroutine\Channel
    {
        return $this->channel;
    }

    /**
     * @param Channel|null $channel
     */
    public function setChannel(?\Swoole\Coroutine\Channel $channel): void
    {
        $this->channel = $channel;
    }

    public function closedChannel() :bool
    {
        if ($this->channel)
        {
            $concurrent = $this->concurrent;
            while ($concurrent--) {
                $this->channel->push(true);
            }
            $this->channel->close();
        }
        return false;
    }

    /**
     * @return int
     */
    public function getConcurrent(): int
    {
        return $this->concurrent;
    }

    /**
     * @param int $concurrent
     */
    public function setConcurrent(int $concurrent): void
    {
        $this->concurrent = $concurrent;
    }
}