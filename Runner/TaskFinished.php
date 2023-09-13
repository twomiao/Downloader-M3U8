<?php
declare(strict_types=1);
namespace Downloader\Runner;

final class TaskFinished
{
    public const FLAG_LOCAL_FILE_EXISTS = 1;
    public const FLAG_SAVE_FILE_SUCCEED = 4;
    public const FLAG_SAVE_FILE_ERROR = 7;
    public const FLAG_DOWNLOAD_ERROR = 2;
    public const FLAG_DOWNLOAD_FILEINFO_ERROR = 3;
    public const FLAG_DOWNLOAD_SUCCEED = 5;
    public const FLAG_DOWNLOAD_FILEINFO_SUCCEED = 6;

    public function __construct(public int $total = 0,
    public int $succeedNum = 0, public int $errors = 0,public int $flag = self::FLAG_DOWNLOAD_FILEINFO_SUCCEED)
    {
        
    }
}