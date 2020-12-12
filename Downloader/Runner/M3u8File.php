<?php
namespace Downloader\Runner;

use Co\Channel;
use Downloader\Runner\Decrypt\DecryptionInterface;

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
     * @var $decryptKey string
     */
    protected $decryptKey = '';

    /**
     *
     * @var $decryptIV string
     */
    protected $decryptIV = '';

    /**
     * 分组ID
     * @var string $groupId
     */
    protected $groupId = '';

    /**
     * @var string $shuffix
     */
    protected $shuffix = 'mp4';

    /**
     * @var DecryptionInterface $decryptInstance
     */
    protected $decryptInstance = null;

    /**
     * @var $decrptyMethod
     */
    protected $decrptyMethod = '';

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
     * [basenmae] => [remoteTs]
     * @var array $tsMap
     */
    protected $bindTsMap = [];

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


    public function setDecryptMethod($decrptyMethod)
    {
        $this->decrptyMethod = $decrptyMethod;
        return $this;
    }

    public function getDecryptMethod()
    {
        return $this->decrptyMethod;
    }

    /**
     * @param int $index
     */
    public function setM3u8Id(int $index = 0): void
    {
        if ($index >= 0) {
            $basename = basename($this->output);
            $m3u8Id = $this->output . "{$index}_{$basename}.{$this->shuffix}";

            $this->m3u8Id = $m3u8Id;
            return;
        }
        $this->m3u8Id = $this->output . date('YmdHis') . ".{$this->shuffix}";
    }


    /**
     * @param DecryptionInterface $decryptInstance
     */
    public function setDecryptInstance(DecryptionInterface $decryptInstance)
    {
        $this->decryptInstance = $decryptInstance;
    }

    /**
     * @return DecryptionInterface
     */
    public function getDecryptInstance()
    {
        return $this->decryptInstance;
    }

    /**
     * @param $suffix string
     */
    public function setShuffix($suffix): void
    {
        $this->shuffix = $suffix;
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
    public function getDecryptKey(): string
    {
        return $this->decryptKey;
    }

    /**
     * @param string $decryptKey
     */
    public function setDecryptKey(string $decryptKey): void
    {
        $this->decryptKey = $decryptKey;
    }

    /**
     * @return string
     */
    public function getDecryptIV(): string
    {
        return $this->decryptIV;
    }

    /**
     * @param string $decryptIV
     */
    public function setDecryptIV(string $decryptIV): void
    {
        $this->decryptIV = $decryptIV;
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
    public function getChannel(): ?Channel
    {
        return $this->channel;
    }

    /**
     * @param Channel|null $channel
     */
    public function setChannel(?Channel $channel): void
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