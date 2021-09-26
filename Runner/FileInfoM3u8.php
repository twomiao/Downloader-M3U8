<?php
declare(strict_types=1);

namespace Downloader\Runner;

/**
 * Class FileInfoM3u8
 * @package Downloader\Runner
 */
class FileInfoM3u8
{
    public static function parser(string $m3u8File)
    {
        if (empty($m3u8File)) {
            return null;
        }

        return new class ($m3u8File)
        {
            private string $dataHeader;
            private string $dataTs;
            private string $m3u8Data;

            public function __construct(string $m3u8Data)
            {
                $this->m3u8Data = $m3u8Data;
                if (!$this->isM3u8File()) {
                    throw DownloaderException::valid('Invalid file content');
                }
                preg_match("@#EXTM3U(.*?)#EXTINF@is", $m3u8Data, $dataHeader);
                $this->dataHeader = trim($dataHeader[1]);
                preg_match("@#EXTINF:(.*?)#EXT-X-ENDLIST@is", $m3u8Data, $dataTs);
                $this->dataTs = trim($dataTs[0]);
            }

            public function isM3u8File(): bool
            {
                $ret = strpos($this->m3u8Data, '#EXTM3U');
                if (is_int($ret)) {
                    return true;
                }
                return false;
            }

            // 加密KEY
            public function getKey()
            {
                preg_match('@URI="(.*?)"@is', $this->dataHeader, $matches);

                return $matches[1] ?? '';
            }

            // 加密方式
            public function getMethodKey()
            {
                preg_match('@EXT-X-KEY:METHOD=(.*?),@is', $this->dataHeader, $matches);

                return $matches[1] ?? '';
            }

            // 版本号
            public function getVersion()
            {
                preg_match('@#EXT-X-VERSION:(\d+)|(\d+\.\d+)@is', $this->dataHeader, $matches);
                return $matches[1] ?? 0;
            }

            // 最大时间
            public function getMaxTime()
            {
                preg_match('@#EXT-X-TARGETDURATION:(\d+)|(\d+\.\d+)@is', $this->dataHeader, $matches);
                return (float)$matches[1] ?? 0;
            }

            // 时间
            public function getTimes()
            {
                preg_match_all("@#EXTINF:(.*?),@is", $this->dataTs, $matches);

                return ($matches[1]) ?? '0';
            }

            // ts
            public function getPathTs()
            {
                preg_match_all("@,(.*?).ts@is", $this->dataTs, $matches);

                return ($matches[0]) ?? '';
            }
        };
    }


}