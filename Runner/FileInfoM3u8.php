<?php
declare(strict_types=1);

namespace Downloader\Runner;

/**
 * Class FileInfoM3u8
 * @package Downloader\Runner
 * @method isM3u8File() bool
 * @method getMethodKey() string
 * @method getSecretKey() string
 * @method getVersion() string
 * @method getMaxTime() float
 * @method getTimes() float
 * @method getPathTs() array
 * @method getTimeArray() array
 */
class FileInfoM3u8
{
    public static function parse(string $m3u8File)
    {
        if (empty($m3u8File)) {
            return null;
        }

        return new class ($m3u8File)
        {
            private string $data;

            /**
             *  constructor.
             * @param string $data 文件内容
             */
            public function __construct(string $data)
            {
                $this->data = $data;
                if (!$this->isM3u8File()) {
                    throw new \InvalidArgumentException('无效M3u8文件内容', 102);
                }
            }

            public function isM3u8File(): bool
            {
                return \strpos($this->data, '#EXTM3U') === 0;
            }

            /**
             * 加密KEY
             * @return string
             */
            public function getSecretKey() : string
            {
                \preg_match('@URI="(.*?)"@is', $this->data, $matches);

                return $matches[1] ?? '';
            }

            /**
             * 加密算法名称
             * @return string
             */
            public function getMethodKey() : string
            {
                \preg_match('@EXT-X-KEY:METHOD=(.*?),@is', $this->data, $matches);

                return $matches[1] ?? '';
            }

            /**
             * 版本号
             * @return int
             */
            public function getVersion() : int
            {
                \preg_match('/#EXT-X-VERSION:(\d+)/is', $this->data, $res);

                return intval($res[1] ?? 0);
            }

            /**
             * 最大时间
             * @return float
             */
            public function getMaxTime() : float
            {
                \preg_match('/#EXT-X-TARGETDURATION:(\d+)/is', $this->data, $res);

                return \floatval($res[1] ?? 0);
            }

            /**
             * 时间集合数组
             * @return array
             */
            public function getTimeArray() : array
            {
                \preg_match_all("@#EXTINF:(.*?),@is", $this->data, $res);

                return $res[1] ?? [] ;
            }

            /**
             * 获取文件视频片段路径
             * @return array
             */
            public function getPathArray() : array
            {
                \preg_match_all("/,(.*?\.ts)/is", $this->data, $res);

                return ($res[1]) ?? [];
            }
        };
    }
}