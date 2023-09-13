<?php

namespace Downloader\Runner;

use Exception;
use RuntimeException;
use SplObjectStorage;
use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

class FFmpegConvertVideoFormatListener
{
    public function onConvertVideoFormat(Event $event)
    {
        $ffmpegPath = Container::make("config")->ffmpeg_bin_path;
        if(!is_file($ffmpegPath))
        {
            return;
        }
        match($class = get_class($event)) {
            FFmpegConvertVideoFormat::class => $this->workers($event->downloadedFiles()),
            default => throw new \Exception("Unknown event {$class}.")
        };
    }

    protected function workers(SplObjectStorage $files)
    {
        $config = Container::make("config");
        foreach($files as $file) {
            if ($file->taskFinished->flag !== TaskFinished::FLAG_SAVE_FILE_SUCCEED) {
                continue;
            }
            $from = $file->tmpFilename();
            $to   = $file->getFilename();

            $command = str_replace(["%from", "%to"], [$from, $to], $config->ffmpeg_bin_path);
            try {
                $process = new Process($command);
                $process->start();
                $error = $process->getErrorOutput();
                if(!$process->isRunning()) {
                    throw new RuntimeException("Process fork error: {$error}");
                }
                warning(sprintf("%s 『%s』视频格式正在转换中......", date('Y-m-d H:i:s'), $file->getBasename()));
                if ($process->wait() !== 0) {
                    throw new RuntimeException(sprintf("Process wait error: %s", $error));
                }
                info(sprintf("%s 『%s』视频格式转换完成.", date('Y-m-d H:i:s'), $file->getBasename()));
            } catch(Exception $e) {
                Container::make("log")->error((string)$e);
            } finally {
                $file->deleteTempFile();
            }
        }
    }
}
