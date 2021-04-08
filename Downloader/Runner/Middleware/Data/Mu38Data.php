<?php
namespace Downloader\Runner\Middleware\Data;

class Mu38Data
{
    /**
     * ts 列表
     * @var string $authKeyUrl
     */
    protected $authKeyUrl;

    /**
     * ts 视频文件加密内容
     * @var string $tsRawData
     */
    protected $tsRawData;

    public function __construct(string $authKeyUrl, $rawData)
    {
        $this->authKeyUrl = $authKeyUrl;
        $this->tsRawData = $rawData;
    }

    /**
     * @return string
     */
    public function getAuthkeyUrl(): string
    {
        return $this->authKeyUrl;
    }

    /**
     * @return string
     */
    public function getRawData(): string
    {
        return $this->tsRawData;
    }
}