<?php  
declare(strict_types=1);
namespace Downloader\Runner;

use Dariuszp\CliProgressBar;
use SplObjectStorage;
use Swoole\Timer;

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

    /**
     * 任务数据
     * @property array<string, FileM3u8> $files
     */
    public function __construct(protected array $files = [])
    {
        
    }

    public function addProgressBar(FileM3u8 $file, CliProgressBar $progressBar) : void {
        $this->movePointer++;
        $this->progressBarMap[spl_object_hash($file)] = $progressBar;
    }

    public function count() : int {
        return \count( $this->progressBarMap );
    }

    public function display() : static {
        // 初始化显示进度条
        foreach($this->progressBarMap as $key => $progressBar)
        {
            $progressBar->display();
            $progressBar->end();
        }
        return $this;
    }

    public function movePointer() : void {
        print ("\33[{$this->movePointer}A");
    }

    public function drawCliMultiProgressBar() : int {
        print ("\33[{$this->movePointer}A");
        $succeed = 0;
        foreach ($this->files as $file)
        {
            if ($this->drawCliProgressBar($file)) {
                $succeed++;
            }
        }
        return $succeed;
    }

    public function drawCliProgressBar(FileM3u8 $file) : bool {
        $progressBar =  $this->progressBarMap[spl_object_hash($file)];
        $progressBar->setCurrentStep($file->taskFinished->succeedNum);
        $flag = false;
        if ($file->taskFinished->errors > 0)
        {
            // 下载失败(30) [变形金刚5]:
            $progressBar->setColorToRed();
        }

        if ($progressBar->getCurrentStep() == $progressBar->getSteps())
        {
            // 下载完成 [变形金刚5]: 
            $progressBar->setColorToGreen();
            $flag = true;
        }
        $progressBar->display();
        $progressBar->end();
        return $flag; 
    }
}