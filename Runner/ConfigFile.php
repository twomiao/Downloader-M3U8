<?php
namespace Downloader\Runner;

use RuntimeException;

final class ConfigFile {
    private array $configData = [
        // "ffmpeg_bin_path" => "/usr/bin/ffmpeg -i %s -vcodec copy -f mp4 %s -acodec copy -codec copy",
        // "ffmpeg_bin_path" => "/usr/bin/ffmpeg -i %s -c:v copy -c:a copy -y %s",
        "ffmpeg_bin_path" => [
            '/usr/bin/ffmpeg',
            '-i',
            '%from',
            '-c:v',
            'copy',
            '-c:a',
            'copy',
            '-y',
            '%to'
        ],
        "count" => 35, // 协程数量
        "http_set" => [
            'retry_num' => 3, // 失败重试3次
            'connect_timeout' => 15,//连接超时，会覆盖第一个总的 timeout
            'write_timeout' => 15,//发送超时，会覆盖第一个总的 timeout
            'read_timeout' => 15,//接收超时，会覆盖第一个总的 timeout
            "keep_alive" => true,
        ]
    ];

    public function __construct(array $config = [])
    {
        $this->configData = array_replace($this->configData, $config);
        array_splice($this->configData, 3);
    }

    public function __set($name, $value)
    {
        if (!is_null($val = $this->configData[$name]??null)) {
           if (is_numeric($val) && (int)$val <= 0) {
                return;
           }
           $this->configData[$name] = is_numeric($value) ? (int)$value : $value;
        }
    }

    public function __get($name)
    {
        $value = $this->configData[(string)$name] ?? "";
        if (is_numeric($value) && (int)$value <= 0)
        {
            throw new RuntimeException("Incorrect configuration file entry [$name => $value]");
        }
        return $value;
    }

    public function __toString() {
        return var_export($this->configData, true);
    }

}
