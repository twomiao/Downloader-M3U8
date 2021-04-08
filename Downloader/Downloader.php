<?php declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';

use Swoole\Runtime;
use Downloader\Runner\Downloader as Downloader;
use Symfony\Component\Console\Application;
use Downloader\Command\StartCommand;

\Swoole\Coroutine::create(function () {
    Runtime::enableCoroutine(true, SWOOLE_HOOK_ALL);

    $application = new Application('Downloader-M3u8', Downloader::VERSION);
    $application->setAutoExit(false);

    $application->add(new StartCommand());
    $application->run();
});