<?php declare(strict_types=1);
require __DIR__ . '/vendor/autoload.php';

use Swoole\Runtime;
use Downloader\Runner\Downloader;
use Symfony\Component\Console\Application;
use function Swoole\Coroutine\run;
use Downloader\Command\M1906Command;
use Downloader\Runner\Command\FileTemplate;

define('DOWNLOAD_DIR', __DIR__ . '/../Downloader');

run(function () {
    Runtime::enableCoroutine(true, SWOOLE_HOOK_ALL);

    $application = new Application(Downloader::PROGRAM_NAME, Downloader::VERSION);
    $application->setAutoExit(false);
    $application->add(new M1906Command()); // 下载 https://www.1905.com/ 视频
    $application->add(new FileTemplate()); // 创建下载模板

    $application->run();
});