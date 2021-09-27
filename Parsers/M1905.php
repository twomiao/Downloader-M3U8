<?php declare(strict_types=1);
namespace Downloader\Parsers;

use Downloader\Runner\Contracts\DecodeVideoInterface;
use Downloader\Runner\Downloader;
use Downloader\Runner\FileException;
use Downloader\Runner\FileM3u8;
use Downloader\Runner\VideoParser;
use Psr\Log\LoggerInterface;


/***
 * Class M1905
 * @package Downloader\Parsers
 * 如果视频本身未加密，不需要实现“解密接口” DecodeVideoInterface
 * 如果需要登录才能下载，请重写HttpRequest 重新绑定对象到容器即可
 */
class M1905 extends VideoParser implements DecodeVideoInterface
{
    /**
     * 下载文件命名
     * @param string $m3u8Url
     * @return string
     */
    protected function filename(string $m3u8Url): string
    {
        return  'demo';
    }

    /**
     * 获取完整ts 地址
     * @param string $m3u8FileUrl
     * @param string $partTsUrl
     * @return string
     */
    public function tsUrl(string $m3u8FileUrl, string $partTsUrl): string
    {
        return dirname($m3u8FileUrl)."/{$partTsUrl}";
    }

    /**
     * 解码视频
     * @param FileM3u8 $fileM3u8
     * @param string $data
     * @param string $remoteTsUrl
     * @return string
     * @throws FileException
     */
    public static function decode(FileM3u8 $fileM3u8, string $data, string $remoteTsUrl): string
    {
        $method = $fileM3u8->getDecryptMethod();
        $key = $fileM3u8->getKey();

        $data = \openssl_decrypt($data, $method, $key, OPENSSL_RAW_DATA);
        if ($data === false) {
            Downloader::getContainer(LoggerInterface::class)->error(
                sprintf("网络地址 %s, 尝试解密方式和秘钥 [%s] - [%s] 解密失败!", $remoteTsUrl, $method, $key)
            );
            throw new FileException(
                sprintf("失败网络地址 %s, 尝试解密方式和秘钥 [%s] - [%s] 解密失败!", $remoteTsUrl, $method, $key)
            );
        }
        return $data;
    }

    /**
     * 秘钥KEY
     * @param FileM3u8 $fileM3u8
     * @return string
     */
    public static function key(FileM3u8 $fileM3u8): string
    {
        return file_get_contents(dirname((string)$fileM3u8) . '/' . $fileM3u8->getKeyFile());
    }

    /**
     * 加密方式
     * @param FileM3u8 $fileM3u8
     * @return string
     */
    public static function method(FileM3u8 $fileM3u8): string
    {
        return 'aes-128-cbc';
    }
}