<?php

declare(strict_types=1);

namespace Downloader\Runner;

use Dariuszp\CliProgressBar;
use Swoole\Coroutine\Channel;

class MultipleProgressBar
{
    /**
     *  ['task1' => progressBar, 'task2' => progressBar, .....]
     * @property array<string,CliProgressBar> $progressBarMap
     */
    protected array $progressBarMap = [];

    /**
     *  指针移动
     * @property int $movePointer
     */
    protected int $movePointer = 0;

    protected ?Channel $saveFileChan = null;

    /**
     * 任务数据
     * @property array<string, FileM3u8> $files
     */
    public function __construct(protected array $files = []) {}

    public function addProgressBar(FileM3u8 $file, CliProgressBar $progressBar): void
    {
        $this->movePointer++;
        $this->progressBarMap[$file->id()] = $progressBar;
    }

    public function count(): int
    {
        return \count($this->progressBarMap);
    }

    public function initDisplay(): static
    {
        // 初始化显示进度条
        foreach($this->progressBarMap as $progressBar) {
            $progressBar->display();
            $progressBar->end();
        }
        return $this;
    }

    public function saveFileChan(Channel $saveFile)
    {
        $this->saveFileChan = $saveFile;
    }


    public function movePointer(): void
    {
        print("\33[{$this->movePointer}A");
    }

    public function display(): void
    {
        $this->movePointer();
        foreach ($this->files as $file) {
            if ($this->drawCliProgressBar($file)) {
                if($file->statistics()->flag === Statistics::DOWNLOAD_OK) {
                    continue;
                }
                $file->statistics()->flag = Statistics::DOWNLOAD_OK;
                $this->saveFileChan?->push($file);
            }
        }
    }

    public function drawCliProgressBar(FileM3u8 $file): bool
    {
        $progressBar =  $this->progressBarMap[$file->id()];
        $progressBar->setCurrentStep($file->statistics()->succeedNum);
        $res = false;
        if ($file->statistics()->errors > 0) {
            $progressBar->setColorToRed();
        }

        if ($progressBar->getCurrentStep() == $progressBar->getSteps()) {
            $progressBar->setColorToGreen();
            $res = true;
        }
        $progressBar->display();
        $progressBar->end();
        return $res;
    }
}
