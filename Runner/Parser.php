<?php
declare(strict_types=1);

namespace Downloader\Runner;

use Psr\Log\LoggerInterface;
use Swoole\Coroutine;
use Swoole\Coroutine\WaitGroup;

/**
 * Class Parser
 * @package Downloader\Runner
 *
 * 20190524/c7dWMD0m/800kb/hls/index.m3u8
 */
abstract class Parser
{
    // [ [M1905::class => [new M3u8File(), new M3u8File()],... ]
    protected static array $m3u8Files = [];

    /**
     * 保存网络中已知秘钥
     * @var array $keyMap
     */
    public static array $decryptKeyMap = [
//          '文件名称' => '秘钥',
//          '文件名称' => '秘钥',
    ];

    /**
     * 下载TS视频
     * @param string $m3u8FileUrl
     * @param string $partTsUrl
     * @return string
     */
    protected abstract static function tsUrl(string $m3u8FileUrl, string $partTsUrl): string;

    /**
     * 保存到本地视频名称
     * @param string $m3u8FileUrl
     * @return string
     */
    protected abstract static function fileName(string $m3u8FileUrl): string;

    /**
     * 秘钥完整URL
     * @param string $m3u8FileUrl
     * @param string $keyUrl
     * @return string
     */
    protected abstract static function getKeyUrl(string $m3u8FileUrl, string $keyUrl): string;

    public static function start(array $m3u8FileUrls)
    {
        $wg = new WaitGroup();

        foreach ($m3u8FileUrls as $m3u8FileUrl) {
            $wg->add();
            // m3u8 data
            Coroutine::create([static::class, 'decodeM3u8File'], $m3u8FileUrl, $wg);
        }

        if ($wg->wait(300) === false) {
            throw new FileException("目标文件分析超时: 300s");
        }

        /********************************
         * M3U8文件分析完成
         * *******************************
         * 返回完整M3U8文件列表
         ********************************/
        return static::$m3u8Files;
    }

    /**
     * 发现秘钥KEY，才会调用此解密方法
     * @param string $data
     * @param string $tsUrl
     * @param string $decryptKey
     * @return string
     * @throws FindKeyException
     */
    public static function decodeData(string $data, string $tsUrl, string $decryptKey): string
    {
        /**
         ********************************
         * 默认解密算法  aes-128-cbc
         ********************************
         *
         * 下载网络数据进行尝试解密
         *
         * ******************************
         */
        if (FileM3u8::$decryptKey && FileM3u8::$decryptMethod) {
            throw new FindKeyException("Find the key, try to decrypt.");
        }
        return $data;
    }

    /**
     * @param string $m3u8FileUrl
     * @param WaitGroup $wg
     * @throws \Exception
     */
    public static function decodeM3u8File(string $m3u8FileUrl, WaitGroup $wg): void
    {
        \Swoole\Coroutine::defer(static fn () => $wg->done());

        // 头+包
        $httpRequest = new HttpRequest($m3u8FileUrl, 'GET', ['CURLOPT_HEADER' => true, 'CURLOPT_NOBODY' => false]);
        try {
            $resp = $httpRequest->send();
        } catch (\Exception $e) {
            echo $e->getMessage() . PHP_EOL;
            return;
        }

        $fileName = static::fileName($m3u8FileUrl);

        try {
            // 解析m3u8文件结构
            $fileInfo = FileInfoM3u8::parser($resp->getData());

            if (empty($fileInfo)) {
                return;
            }

            $m3u8File = new FileM3u8($resp->getHeaders(), $fileInfo, $fileName);
            $m3u8File->addM3u8Url($m3u8FileUrl);
            // 找到KEY 文件，尝试获取网络已知密码
            $getKeyPassword = function ($m3u8FileUrl, $key) {
                $keyPassword = "";
                if (!empty($key)) {
                    $keyUrl = static::getKeyUrl($m3u8FileUrl, $key);
                    $keyPassword = file_get_contents($keyUrl);
                }
                return $keyPassword;
            };
            static::$decryptKeyMap[$fileName] = $getKeyPassword($m3u8FileUrl, $fileInfo->getKey());

        } catch (\Exception $e) {
            Downloader::getContainer(LoggerInterface::class)->error($e->getMessage());
            return;
        }

        // 创建分片对象
        [$pathTsMap, $times, $putFileDir] = [$fileInfo->getPathTs(), $fileInfo->getTimes(), $m3u8File->getPutFileDir()];
        foreach ($pathTsMap as $key => $pathTs) {
            $playTime = $times[$key];
            $m3u8File->addPartTs(static::newPartTs($putFileDir, (float)$playTime, $m3u8FileUrl, $pathTs));
        }

        //[M1905::class =>['文件名称'=>new object(), ....]];
        static::$m3u8Files[static::class][$m3u8File->getFilename()] = $m3u8File;
    }

    /**
     * 创建分片对象
     * @param string $putFileDir
     * @param float $playTime
     * @param string $m3u8FileUrl
     * @param string $pathTs
     * @return PartTs
     */
    protected static function newPartTs(
        string $putFileDir,
        float $playTime,
        string $m3u8FileUrl,
        string $pathTs
    )
    {
        $remote_ts_url = static::tsUrl($m3u8FileUrl, trim(ltrim($pathTs, ',')));
        return new PartTs($putFileDir, $playTime, $remote_ts_url);
    }
}