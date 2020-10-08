<?php
namespace Downloader\Miaotwo;

class MovieParser
{
    /**
     * ts 已分析完成的队列
     * @var array
     */
    protected $tsQueue = [];

    /**
     * 下载数量统计
     * @var int
     */
    protected $downloads = 0;

    /**
     * M3U8目标地址
     * @var string
     */
    protected $m3u8Url;

    /**
     * M3U8文件内容
     * @var bool|string
     */
    protected $m3u8Content = '';

    /**
     * 解析器接口
     * @var MovieParserInterface|null
     */
    protected $movieParser = null;

    /**
     * MovieParser constructor.
     * @param string $m3u8Url M3U8目标地址
     * @param MovieParserInterface $movieParser  用于自定义解析器
     */
    public function __construct(string $m3u8Url, MovieParserInterface $movieParser)
    {
        if (!preg_match('/http[s]*:\/\/([\w.]+\/?)\S*/Uis', $m3u8Url)) {
            Logger::create()->error("{$m3u8Url} 不是一个有效地地址", "[ Error ] ");
        }
        $this->m3u8Url = $m3u8Url;
        $this->movieParser = $movieParser;

        $retries = 0;
        while ($retries <= 3) {
            $this->m3u8Content = HttpClient::get($m3u8Url);
            if ($this->m3u8Content === false) {
                Logger::create()->error("{$m3u8Url} 解析地址失败", '[ Error ] ');
            }

            if (strlen($this->m3u8Content) > 0) {
                break;
            }
            ++$retries;
        }
        return $this;
    }

    /**
     * 解析过程
     * @return array
     */
    public function parsed()
    {
        if (strlen($this->m3u8Content) > 0) {
            preg_match_all("#,\s(.*?).ts#is", $this->m3u8Content, $matches);
            foreach ($matches[1] as $id => $ts) {
                $ts = trim($ts) . '.ts';
                $this->tsQueue[] = $this->movieParser->parsedTsUrl($this->m3u8Url, $ts);
                $this->downloads = ++$id;
            }
        }
        return $this->tsQueue;
    }

    /**
     * 队列统计
     * @return int
     */
    public function getDownloads()
    {
        return $this->downloads;
    }

    /**
     * 解析器得到的队列内容
     * @return array
     */
    public function getParserTsQueue()
    {
        return $this->tsQueue;
    }
}