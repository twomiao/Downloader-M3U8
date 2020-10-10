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
        $totalNum = $this->downloader->getQueueLength();
        // 已下载任务队列长度
        $downloadedCount = $this->downloader->getDownloadeCount();
        // 已下载数量
        $downloaded = $this->tsQueueToMp4Video($tasks, $downloadedCount);

        if (!is_file($this->exportMp4)) {
            Logger::create()->error("下载失败: {$this->exportMp4}, 下载文件数量: {$totalNum}/{$downloaded}", '[ Error ] ');
            exit(244);
        }

        $fileSize = Utils::fileSize(
            $this->downloader->getDownoadedSize()
        );
        echo "\n [ Done ] 下载数据完成: {$totalNum}/{$downloaded}个, 文件大小: {$fileSize}.\n";
        echo " [ Done ] 视频下载成功,已保存到本地: {$this->exportMp4}.\n";
    }

    private function tsQueueToMp4Video($tasks, $downloadedCount): int
    {
        echo "\n";
        ksort($tasks);
        foreach ($tasks as $rate => $task) {
            clearstatcache();
            if (is_file($task)) {
                file_put_contents($this->exportMp4, file_get_contents($task), FILE_APPEND);
                unlink($task);
                ++$rate;
                printf(" \033[0;32m[ Saving ] 视频保存进度: %d%%\033[0m \r",
                    $rate / $downloadedCount * 100
                );
            }
        }
        return count($tasks);
    }

    public static function darw($percent, $totalCount, $downloadSpeed)
    {
        printf(" [ Downloading ] \e[0;35m正在下载数据: [%-50s] %d%%, %s, %s个 \r\e[0m",
            str_repeat('=', $percent / $totalCount * 50),
            $percent / $totalCount * 100,
            "网速: {$downloadSpeed}",
            "文件数量: {$totalCount}/{$percent}"
        );
    }
}