<?php
namespace Downloader\Runner;

use Swoole\Coroutine\System;

class CreateFFmpegVideoListener
{
    const METHOD_NAME = 'createBinaryFile';

    public function createBinaryFile(CreateVideoFileEvent $event) {
        $file = $event->getFileM3u8();
//        $suffix = $event->getSuffix();
        // 本地临时文件名称
        $realFile = $file->getRealFilename();
        $tempDir  = $file->getTempDir();
        $tempFile = $file->getTempFilePath();

        $ffmpegTextFile = \dirname($file->getTempFilePath()). '/'.$file->getTempFilename().'.txt';

        print PHP_EOL;
//        print date('Y-m-d H:i:s'). " >正在生成[FFMPEG 命令]文件: {$ffmpegTextFile}.".PHP_EOL;
        // ffmpeg -f concat -safe 0 -i 1.log  -acodec copy -vcodec copy -absf aac_adtstoasc output.mp4
        if ( !$success = self::ffmpegFilePath($file, $ffmpegTextFile ) ) {
            throw new \Exception("FFMPEG命令生成失败: {$file->getTempFilePath()}.");
        }
        print "正在创建本地视频文件 [{$realFile}].".PHP_EOL;
//        $command = "/usr/bin/ffmpeg -f concat -safe 0 -i {$ffmpegTextFile}  -c copy  {$tempFile} >/dev/null 2>&1";
        $command = "/usr/bin/ffmpeg -f concat -safe 0 -i {$ffmpegTextFile}  -c copy  {$tempFile} >/dev/null 2>&1";
//        $command = "/usr/bin/ffmpeg -f concat -safe 0 -i {$ffmpegTextFile}  -c copy  {$filename}";
        print date('Y-m-d H:i:s'). " >正在执行FFMPEG命令, 创建视频文件: {$realFile}.\n";
        try {
            if( !$res = System::exec($command)) {
                throw new \Exception("FFMPEG命令执行失败：{$command}");
            }
            // 拷贝完成, 执行删除
            \copy($tempFile, $realFile);
        } finally {
           \is_file($ffmpegTextFile) && \unlink($ffmpegTextFile);
            @\unlink($tempFile);
        }
        print date('Y-m-d H:i:s'). " >正在删除[{$realFile}]的分片文件 .....".PHP_EOL;
        $this->deleteFiles($file);
        is_dir($tempDir) && \rmdir($tempDir);
        print date('Y-m-d H:i:s'). " >已生成视频文件: {$realFile}.".PHP_EOL;
        $file->setState(FileM3u8::STATE_SUCCESS);
    }

    /**
     * 删除分片文件
     * @param FileM3u8 $fileM3u8
     * @return bool
     */
    protected function deleteFiles(FileM3u8 $fileM3u8) : bool {
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
        /**
         * @var $file TransportStreamFile
         */
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