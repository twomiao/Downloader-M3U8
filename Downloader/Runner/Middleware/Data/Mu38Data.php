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
     * @var string $tsEncryptedData
     */
    protected $tsEncryptedData;

    public function __construct(string $dataMu38url, string $tsEncryptedData)
    {
        $this->dataMu38url = $dataMu38url;
        $this->tsEncryptedData = $tsEncryptedData;
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
    public function getTsEncryptedData(): string
    {
        return $this->tsEncryptedData;
    }

}