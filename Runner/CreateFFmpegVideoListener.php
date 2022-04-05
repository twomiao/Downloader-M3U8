<?php
namespace Downloader\Runner;

use Swoole\Coroutine\System;

class CreateFFmpegVideoListener
{
    const METHOD_NAME = 'createBinaryFile';

    public function createBinaryFile(CreateVideoFileEvent $event) {
        $file = $event->getFileM3u8();
        // 文件存在停止创建视频文件
        if ($file->exists()) {
            return;
        }
//        $suffix = $event->getSuffix();
        // 本地文件名称
        $filename = "{$file->getFilePath()}";

        $ffmpegTextFile = \dirname($file->getFilePath()). '/'.$file->getFilename().'.txt';

        print PHP_EOL;
        print date('Y-m-d H:i:s'). " >正在生成[FFMPEG 命令]文件: {$ffmpegTextFile}.".PHP_EOL;
        // ffmpeg -f concat -safe 0 -i 1.log  -acodec copy -vcodec copy -absf aac_adtstoasc output.mp4
        if ( !$success = self::ffmpegFilePath($file, $ffmpegTextFile ) ) {
            throw new \Exception("FFMPEG命令生成失败: {$file->getFilePath()}.");
        }
//        $command = "/usr/bin/ffmpeg -f concat -safe 0 -i {$ffmpegTextFile}  -acodec copy -vcodec copy -absf aac_adtstoasc {$filename} >/dev/null 2>&1";
        $command = "/usr/bin/ffmpeg -f concat -safe 0 -i {$ffmpegTextFile}  -c copy  {$filename} >/dev/null 2>&1";
//        print date('Y-m-d H:i:s'). " >正在执行FFMPEG 命令[{$command}], 创建视频文件: {$filename}.\n";
        print date('Y-m-d H:i:s'). " >正在执行FFMPEG命令, 创建视频文件: {$filename}.\n";
        try {
            if( !$res = System::exec($command)) {
                throw new \Exception("FFMPEG命令执行失败：{$command}");
            }
        } finally {
           \is_file($ffmpegTextFile) && \unlink($ffmpegTextFile);
        }
        print date('Y-m-d H:i:s'). " >正在删除[{$filename}]的分片文件 .....".PHP_EOL;
        $this->deleteFiles($file);
        print date('Y-m-d H:i:s'). " >已生成视频文件: {$filename}.".PHP_EOL;
    }

    /**
     * 删除分片文件
     * @param FileM3u8 $fileM3u8
     * @return bool
     */
    protected function deleteFiles(FileM3u8 $fileM3u8) : bool {
        if (is_null($fileM3u8)) {
            return false;
        }
        foreach ($fileM3u8 as $file) {
            if ($file instanceof TransportStreamFile) {
                if (!$file->delete()) {
                    return false;
                }
            }
        }
        return true;
    }

    protected static function ffmpegFilePath(FileM3u8 $fileM3u8, string $ffmpegTextFile) :bool {
        foreach ($fileM3u8 as $file)
        {
            $result = System::writeFile($ffmpegTextFile, "file '".$file->getFilePath()."'\n", FILE_APPEND );
            if ($result === false) {
                \clearstatcache();
                \unlink($ffmpegTextFile);
                return false;
            }
        }
        return true;
    }
}