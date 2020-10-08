<?php

namespace Downloader\Miaotwo;

class ProgressBar
{
    /**
     * @var Downloader
     */
    private $downloader;

    /**
     * 保存视频文件到本地电脑
     * @var string
     */
    private $exportMp4;

    /**
     * 创建进度条
     * ProgressBar constructor.
     * @param Downloader $downloader
     */
    public function __construct(Downloader $downloader)
    {
        $this->downloader = $downloader;
        // 保存视频到本地电脑
        $this->exportMp4 = $downloader->getExportPath() . "/" . time() . '.mp4';
    }

    public function paint()
    {
        // 已下载队列内容
        $tasks = $this->downloader->getQueue();
        // 已下载任务队列长度
        $queueLength = $this->downloader->getQueueLength();

        // 已下载数量
        $downloaded = $this->tsQueueToMp4Video($tasks, $queueLength);

        if (!is_file($this->exportMp4)) {
            Logger::create()->error("下载失败: {$this->exportMp4}, 下载文件数量: {$downloaded}", '[ Error ] ');
            exit(255);
        }

        $fileSize = Utils::fileSize($this->exportMp4);
        echo "\n [ Done ] 下载文件完成: {$downloaded}个, 文件大小: {$fileSize}.\n";
        echo " [ Done ] 视频下载成功,已保存到本地: {$this->exportMp4}.\n";
    }

    private function tsQueueToMp4Video($tasks, $queueLength): int
    {
        ksort($tasks);
        foreach ($tasks as $rate => $task) {
            clearstatcache();
            if (is_file($task)) {
                file_put_contents($this->exportMp4, file_get_contents($task), FILE_APPEND);
                unlink($task);
                ++$rate;
                printf(" \033[0;32m[ Saving ] 视频保存进度: %d%%\033[0m \r", $rate / $queueLength * 100);
            }
        }
        return count($tasks);
    }
}