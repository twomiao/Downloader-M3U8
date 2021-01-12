<?php
namespace Downloader\Runner\Middleware\Data;

class Mu38Data
{
    /**
     * ts 列表
     * @var string $dataMu38url
     */
    protected $dataMu38url;

    /**
     * ts 视频文件加密内容
     * @var string $tsRawData
     */
    protected $tsRawData;

    public function __construct(string $dataMu38url, $rawData)
    {
        $this->dataMu38url = $dataMu38url;
        $this->tsRawData = $rawData;
    }

    /**
     * @return string
     */
    public function getDataMu38url(): string
    {
        return $this->dataMu38url;
    }

    /**
     * @return string
     */
    public function getRawData(): string
    {
        return $this->tsRawData;
    }

}