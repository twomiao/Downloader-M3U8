<?php
declare(strict_types=1);

namespace Downloader\Runner;

use Exception;
use RuntimeException;
use Symfony\Contracts\EventDispatcher\Event;
use Swoole\Coroutine;
use Symfony\Component\Process\Process;

class SaveFileListener
{
    public function onSaveFile(Event $event)
    {
        Coroutine::defer(static fn () => static::clearTempFile($event->file));
        try {
            if($event instanceof SaveFile && $event->file->save()) {
                // 进行格式转换
                static::ffmpegConvertVideoFormat($event->file);
            }
        } catch(Exception $e) {
            Container::make("log")->error((string)$e);
        }
    }

    protected static function clearTempFile(FileM3u8 $file): void
    {
        $file->deleteTempFile();
        Downloader::$fileCount--;
    }

    protected static function ffmpegConvertVideoFormat(FileM3u8 $file): void
    {
        $from = $file->tmpFilename();
        $ffmpegConfig = Container::make("config")->ffmpeg_bin_path;
        $to = (string)$file;
        \reset($ffmpegConfig);
        $bin = \current($ffmpegConfig);
        \clearstatcache();
        if(!is_file($bin)) {
            return;
        }

        $command = str_replace(["%from", "%to"], [$from, $to], $ffmpegConfig);
        try {
            $process = new Process($command);
            $process->start();
            $error = $process->getErrorOutput();
            if(!$process->isRunning()) {
                throw new RuntimeException("Process fork error: {$error}");
            }

            if ($process->wait() !== 0) {
                throw new RuntimeException(sprintf("Process wait error: %s", $error));
            }
        } catch(Exception $e) {
            Container::make("log")->error((string)$e);
        }
    }
}
