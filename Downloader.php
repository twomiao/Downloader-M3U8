<?php declare(strict_types=1);
require __DIR__ . '/vendor/autoload.php';

use Swoole\Process;
use Swoole\Runtime;
use Downloader\Runner\Downloader;
use Symfony\Component\Console\Application;
use Downloader\Command\M1905Command;
use function Swoole\Coroutine\run;

define('DOWNLOAD_DIR', __DIR__ . '/../Downloader');

run(function () {
    Runtime::enableCoroutine(true, SWOOLE_HOOK_ALL);

    $application = new Application(Downloader::PROGRAM_NAME, Downloader::VERSION);
    $application->setAutoExit(false);
    $application->add(new M1905Command()); // 下载 https://www.1905.com/ 视频
    $application->add(new \Downloader\Command\M1906Command()); // 下载 https://www.1905.com/ 视频
    $application->add(new \Downloader\Command\CopyCommand()); // 下载 https://www.1905.com/ 视频
    $application->add(new \Downloader\Command\M1907Command()); // 下载 https://www.1905.com/ 视频
//    $application->add(new OtherCommand()); // 其它网站视频

    $application->run();
});