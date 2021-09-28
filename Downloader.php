<?php declare(strict_types=1);
require __DIR__ . '/vendor/autoload.php';

use Swoole\Runtime;
use Downloader\Runner\Downloader;
use Symfony\Component\Console\Application;
use Downloader\Command\M1905Command;
use function Swoole\Coroutine\run;

define('DOWNLOAD_DIR', __DIR__ . '/../Downloader');

run(function () {
    Runtime::enableCoroutine(true, SWOOLE_HOOK_ALL);

    $application = new Application(Downloader::APP_NAME, Downloader::VERSION);
    $application->setAutoExit(false);
    $application->add(new M1905Command());
    $application->run();
});