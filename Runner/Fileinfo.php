<?php
declare(strict_types=1);

namespace Downloader\Runner;

use RuntimeException;

class Fileinfo
{
    public function __construct(
        protected string $secretKey,
        protected string $methodKey,
        protected int $version,
        protected array $timeList, 
        protected array $pathList){}

    public function getSecretKey(): string {
        return $this->secretKey;   
    }

    public function getMethodKey():string  {
        return $this->methodKey;
    }

    public function getVersion(): int {
        return $this->version;
    }

    public function formatSecond() : string {
        $second = array_sum($this->timeList);
        $seconds = round( $second , 0);
        $hour = intval($seconds / 3600);
        $min = intval($seconds % 3600 / 60);
        $second = round($seconds % 3600 % 60);

        return sprintf("%s:%s:%s",
            str_pad((string)$hour, 2, '0', STR_PAD_LEFT),
            str_pad((string)$min, 2, '0', STR_PAD_LEFT),
            str_pad((string)$second, 2, '0', STR_PAD_LEFT)
        );
    }

    public function getPathList():array {
        return $this->pathList;
    }

}