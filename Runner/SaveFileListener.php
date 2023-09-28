<?php
declare(strict_types=1);
namespace Downloader\Runner;

use Exception;
use RuntimeException;
use Symfony\Contracts\EventDispatcher\Event;
use Swoole\Coroutine;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

class SaveFileListener
{
    public function onSaveFile(Event $event)
    {
        Coroutine::defer(static fn () => Downloader::$fileCount--);
        try {
            if($event instanceof SaveFile && $event->file->save()) {
                // 进行格式转换
                static::ffmpegConvertVideoFormat($event->file);
            }
        } catch(Exception $e) {
            Container::make("log")->error((string)$e);
        }
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
        } finally {
            $file->deleteTempFile();
        }
    }
}
