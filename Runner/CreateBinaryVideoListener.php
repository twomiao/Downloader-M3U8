<?php
namespace Downloader\Runner;

use Swoole\Coroutine\System;

/**
 * 不需要安装FFMPEG程序，直接存储为二进制文件
 * Class CreateBinaryVideoListener
 * @package Downloader\Runner
 */
class CreateBinaryVideoListener
{
    const METHOD_NAME = 'createBinaryFile';

    public function createBinaryFile(CreateVideoFileEvent $event) {
        $file = $event->getFileM3u8();
        // 文件存在停止创建视频文件
        if ($file->exists()) {
            return;
        }
        try {
//            $suffix = $event->getSuffix();

            $local_file = "{$file->getFilePath()}";
            $handle = fopen($local_file, "wb");
            if (is_resource($handle)) {
                /**
                 * @var $file TransportStreamFile
                 */
                foreach ($file as $streamFile) {
                    if ($streamFile instanceof TransportStreamFile)
                    {
                        // 读取已经下载的视频片段
                        $filepath = $streamFile->getFilePath();
//                        $fileData = \file_get_contents($filepath);
                        $fileData = System::readFile($filepath);
                        if ($fileData === false)  {
                            throw new \Exception(swoole_strerror(swoole_last_error(), 9), swoole_last_error());
                        }
                        if($file->isEncryptFile()) {
                            $fileData = $file->decrypt($fileData, $file);
                        }
                        \fwrite($handle, $fileData, \strlen($fileData));
                        \fflush($handle);
                        // 删除文件
                        $streamFile->delete();
                    }
                }
                $file->setState(FileM3u8::STATE_SUCCESS);
            }
        } finally {
            if (is_resource($handle)) {
                \fclose($handle);
            }
        }
    }
}